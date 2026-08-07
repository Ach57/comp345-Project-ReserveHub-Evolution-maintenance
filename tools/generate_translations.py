import json
import re
from pathlib import Path

from deep_translator import GoogleTranslator
from deep_translator.exceptions import TranslationNotFound

# Configuration
TRANSLATIONS_DIR = Path("json")
OUTPUT_FILE = Path("sql/migrate_add_translations.sql")

# Rows per INSERT statement. Keeps memory bounded and improves MySQL performance.
BATCH_SIZE = 500

# Languages to generate.
# The key is the ISO language code used by your database.
LANGUAGES = {
    "es": "es",
    "de": "de",
    "pt": "pt",
    "ar": "ar",
    "ja": "ja",
    "ko": "ko",
    "zh": "zh-CN",
}

# Helpers
def sql_escape(value: str) -> str:
    """
    Escape a string for use inside a MySQL string literal.
    """
    return (
        value
        .replace("\\", "\\\\")
        .replace("'", "''")
    )

def translate_text(
    text: str,
    target_language: str,
    cache: dict
) -> str:
    """Translate text, using a cache to avoid redundant API calls."""
    cache_key = (target_language, text)

    if cache_key in cache:
        return cache[cache_key]

    try:
        translated = GoogleTranslator(source="en", target=target_language).translate(text)
    except TranslationNotFound:
        # Short/all-caps UI labels (e.g. "HOME") can't be translated — keep English.
        translated = None

    # GoogleTranslator can also return None for untranslatable strings.
    if translated is None:
        translated = text

    cache[cache_key] = translated
    return translated


def flush_batch(f, batch: list[str]) -> None:
    """Write accumulated rows as a single INSERT statement, then clear the batch."""
    if not batch:
        return
    f.write(
        "INSERT IGNORE INTO `translations`\n"
        "    (`language_code`, `page_key`, `translation_key`, `translation_value`)\n"
        "VALUES\n"
    )
    f.write(",\n".join(batch))
    f.write(";\n\n")
    batch.clear()

def extract_page_name(filename: str) -> str | None:
    """
    Extract the page name from filenames such as:

        about-en.json
        home-en.json

    Returns:

        about
        home
    """

    match = re.match(r"^(.+)-en\.json$", filename)

    if not match:
        return None

    return match.group(1)

# SQL Generation
def generate_sql():
    cache = {}
    total_rows = 0
    batch: list[str] = []

    english_files = sorted(
        TRANSLATIONS_DIR.glob("*-en.json")
    )

    if not english_files:
        raise FileNotFoundError(
            f"No English translation files found in {TRANSLATIONS_DIR}"
        )

    total_files = len(english_files)
    print(f"Found {total_files} English files.")
    print(f"Languages: {', '.join(LANGUAGES.keys())}")
    print()

    OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)

    with OUTPUT_FILE.open("w", encoding="utf-8") as f:
        f.write(
            "-- ============================================================\n"
            "-- GENERATED FILE\n"
            "-- DO NOT EDIT MANUALLY\n"
            "--\n"
            "-- Generated from English JSON translation files.\n"
            "-- ============================================================\n\n"
        )

        for file_idx, json_file in enumerate(english_files, 1):
            page_key = extract_page_name(json_file.name)

            if page_key is None:
                continue

            print(f"[{file_idx}/{total_files}] Processing: {json_file.name}")

            with json_file.open("r", encoding="utf-8-sig") as jf:
                translations = json.load(jf)

            if not isinstance(translations, dict):
                raise ValueError(
                    f"{json_file} must contain a JSON object."
                )

            for translation_key, english_value in translations.items():

                if not isinstance(english_value, str):
                    raise ValueError(
                        f"{json_file}: "
                        f"{translation_key} must contain a string value."
                    )

                # ------------------------------------------------
                # English
                # ------------------------------------------------
                batch.append(
                    "  ('{lang}', '{page}', '{key}', '{value}')".format(
                        lang="en",
                        page=sql_escape(page_key),
                        key=sql_escape(translation_key),
                        value=sql_escape(english_value),
                    )
                )
                total_rows += 1

                # Other languages
                for language_code, target_language in LANGUAGES.items():

                    print(
                        f"  Translating "
                        f"{translation_key} → {language_code}"
                    )

                    translated_value = translate_text(
                        english_value,
                        target_language,
                        cache
                    )

                    batch.append(
                        "  ('{lang}', '{page}', '{key}', '{value}')".format(
                            lang=sql_escape(language_code),
                            page=sql_escape(page_key),
                            key=sql_escape(translation_key),
                            value=sql_escape(translated_value),
                        )
                    )
                    total_rows += 1

                # Flush to disk when the batch is full.
                if len(batch) >= BATCH_SIZE:
                    flush_batch(f, batch)
                    print(f"  Flushed batch — {total_rows} rows written so far.")

        # Flush any remaining rows after all files are processed.
        flush_batch(f, batch)

    print()
    print("========================================")
    print("Translation generation complete!")
    print("========================================")
    print(f"Files processed: {total_files}")
    print(f"Languages generated: {len(LANGUAGES) + 1}")
    print(f"SQL rows generated: {total_rows}")
    print(f"Unique translations cached: {len(cache)}")
    print(f"Output: {OUTPUT_FILE}")

# Entry point
if __name__ == "__main__":
    generate_sql()
