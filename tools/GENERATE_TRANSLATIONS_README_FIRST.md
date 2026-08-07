## Translation SQL Generator

This script generates the SQL migration file containing the website's translations.

It reads the existing English translation files from the `json/` directory (for example, `about-en.json`), translates their values into the configured target languages, and generates a single SQL file at:

```text
sql/migrate_add_translations.sql
```

The translation keys are kept unchanged, while only the displayed text values are translated. The generated SQL can then be used to populate the `translations` database table.

### Setup

Create a Python virtual environment before running the script:

```bash
python3 -m venv .venv
```

Activate the virtual environment:

**macOS / Linux:**

```bash
source .venv/bin/activate
```

**Windows:**

```bash
.venv\Scripts\activate
```

Install the required dependency:

```bash
pip install deep-translator
```

### Running the Script

Once the virtual environment is activated:

```bash
python scripts/generate_translations.py
```

The script will read the English JSON files, generate the configured translations, and create:

```text
sql/migrate_add_translations.sql
```

The generated SQL file is marked as generated and should not be manually edited. To change translations or add languages, update the source files/configuration and regenerate the SQL.
