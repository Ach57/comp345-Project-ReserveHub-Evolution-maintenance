import json, os, re


if __name__ == "__main__":
    
    json_dir = "json"
    files = sorted(os.listdir(json_dir))

    entries = {}
    for fname in files:
        m = re.match(r'^(.+)-([a-z]{2})\.json$', fname)
        if not m: continue
        page, lang = m.group(1), m.group(2)
        with open(os.path.join(json_dir, fname), encoding='utf-8-sig') as f:
            data = json.load(f)
        entries[(page, lang)] = data

    pages = sorted(set(p for p,l in entries))
    langs = sorted(set(l for p,l in entries))

    print("Pages:", pages)
    print("Langs:", langs)
    print("Total rows:", sum(len(v) for v in entries.values()))


    # ---------------------------------------------------------------------------
    # Pages: ['about', 'admin', 'contact', 'help-center', 'index', 'login-signup', 'policy', 'profile', 'reset-password', 'restaurant', 'search', 'terms', 'vendor']
    # Langs: ['en', 'fr']
    # Total rows: 1952
    # ---------------------------------------------------------------------------