gurt# Roadmapa semestrální práce KIV/WEB: KeepTrack Enterprise

> **Projekt:** Správa financí OSVČ (KIV/WEB)  
> **Student:** Ernst Christoph Leschka (A25B0266P)  
> **Cvičící:** Michal Nykl  
> **Cíl:** Plných 60 bodů ke zkoušce + odevzdání do Vánoc (+10 b bonus)

---

## 1. Bodová strategie (Cíl: 60+ bodů s rezervou)

| Kategorie | Položka | Max / Očekávané body | Realizace v projektu |
| :--- | :--- | :---: | :--- |
| **Povinné požadavky** | MVC architektura (OOP pro M a C, oddělené vrstvy) | **5 b** | Čisté MVC, `Src\Controllers`, `Src\Models`, `Src\Core\Router` |
| | Responzivní design (PC i mobil, funkční tabulky) | **5 b** | Bootstrap 5/6 responzivní layout, flexbox, responzivní tabulky (`table-responsive`) |
| | Kvalita webu (plně funkční aplikace, žádná demoverze) | **6 b** | Kompletní CRUD OSVČ, transakce, validace, provázanost dat |
| | Kvalita designu (propracovaný a jednotný design) | **7 b** | Bootstrap UI komponenty, karty, modaly, alerty, dashboard widgety |
| | Hashování hesel (Bcrypt / Argon2) | **1 b** | `password_hash($pass, PASSWORD_BCRYPT)` |
| | Ošetření útoků (SQL Injection, XSS) | **1 b** | PDO prepared statements (všude), Twig auto-escaping |
| **Volitelná rozšíření** | Předvedení a odevzdání **"do Vánoc"** | **10 b** | Předvedení na posledním prezenčním cvičení |
| | SuperAdmin ("neovlivnitelnost adminů") | **4 b** | Role `superadmin`; běžný admin nemůže spravovat ani mazat jiné adminy |
| | Použití namespaces / jmenné prostory | **1 b** | PSR-4 pod `Src\` v celém projektu |
| | Unikátní názvy souborů po uploadu | **2 b** | `hash('sha256', uniqid() . bin2hex(random_bytes(4))) . '.' . $ext` |
| | Bootstrap (využití alertů, ikon, modalů) | **4 b** | Bootstrap + Bootstrap Icons |
| | JavaScript & jQuery (popř. vanilla JS) | **2 b** | Dynamické řádky položek faktur, validace, obsluha modalů |
| | AJAX | **2 b** | Asynchronní mazání, editace bez reloadu, live validace klíče |
| | Twig (použit napříč celým projektem) | **4 b** | Dědičnost šablon (`layout.twig`), partials, filtry |
| | Vlastní FullREST API endpoint | **4 b** | Kompletní CRUD endpoint `/api/clients` s JSON vstupy i výstupy (GET, POST, PUT, DELETE) |
| | WYSIWYG editor (např. Quill.js) | **3–4 b** | Bohatý editor poznámek k fakturám nebo výdajům |
| | Composer | **1 b** | Správa závislostí (Twig, autoloader) |
| | Docker (běhové prostředí PHP + MySQL + Composer) | **2–3 b** | `Dockerfile` a `docker-compose.yaml` |
| | GIT (GitHub / GitLab) | **1 b** | Správa verzí v gitovém repozitáři |
| **CELKEM** | | **~66 b** | **Maximum 60 bodů přenositelných ke zkoušce** |

---

## 2. Kritické penalizace, kterým se musíme vyhnout

- **Nevalidní HTML (-10 b):** Všechny šablony musí projít [W3C HTML validátorem](https://validator.w3.org/nu/). Žádné chybějící tagy, chybné atributy ani vnoření blokových elementů do řádkových.
- **Chyby nebo varování v PHP (-5 až -10 b):** Zapnuté striktní typování (`declare(strict_types=1)`), žádné `E_NOTICE` / `E_WARNING` (např. neošetřené indexy v `$_POST` či `$_SESSION`).
- **Chybí upload souborů (-4 b):** Nykl explicitně penalizuje absenci uploadu souborů. Implementujeme **upload účtenek/dokladů k výdajům**.
- **Databáze nemá alespoň 3 tabulky (-10 b):** Projekt obsahuje 9 tabulek.
- **Nepřehledný kód / bez komentářů (-až 5 b):** PHPDoc komentáře u všech tříd a metod, čistý kód.

---

## 3. Detailní implementační fáze

### Fáze 1: Databáze a základní model (Hotovo / Rychlá úprava)
- [x] Základní schéma z DB1 (`users`, `clients`, `client_types`, `invoices`, `invoice_items`, `payments`, `invoice_payment`).
~~- [ ] Doplnit tabulku `expense_categories (id, name)` a propojit vazbou do `expenses.category_id`.~~
- [x] Rozšířit `expenses` o sloupce pro upload účtenek: `receipt_filename`, `receipt_original_name`, `receipt_mime`.
- [x] Vložit výchozí role do `roles`: `superadmin`, `admin`, `osvc`, `accountant`.
- [x] Vytvořit ucelený instalační a seed skript `sql/install.sql` obsahující ukázková data pro všechny role a scénáře.

### Fáze 2: Autentizace, role a SuperAdmin
- [x] Registrace:
  - Varianta A: OSVČ (standardní registrace).
  - Varianta B: Účetní (vyžaduje platný `accountant_key`).
- [x] Profil OSVČ:
  - Generování klíče pro účetního (`bin2hex(random_bytes(8))`) s časovou expirací.
  - Zobrazení aktuálně přiřazeného účetního a možnost odebrání přístupu.
- [x] Řízení přístupu (RBAC):
  - Pomocná metoda v `BaseController`: `requireRole(array $roles)`.
  ~~- SuperAdmin ochrana: Pouze `superadmin` smí měnit role nebo mazat uživatele s rolí `admin`. Běžný admin smí spravovat pouze OSVČ a účetní.~~

### Fáze 3: Jádro aplikace (OSVČ CRUD & Byznys logika)
- [x] **Klienti:**
  - Dokončení `ClientController` a šablon (vytvoření, úprava, smazání).
  - AJAX načítání detailu a mazání s potvrzením.
- [x] **Faktury a položky (Master-Detail):**
  - Vystavení faktury s dynamickým přidáváním/odebíráním položek přes JavaScript.
  - Zápis do DB v jedné atomické transakci (`$db->beginTransaction()`).
  - Výpočet celkové částky z položek faktury.
- [x] **Platby a párování s fakturami:**
  - Záznam platby s okamžitým přiřazením k jedné či více fakturám v jedné transakci (`invoice_payment`).
  - Automatická kontrola a nastavení `invoices.paid = 1`, pokud je faktura plně uhrazena.
- [x] **Výdaje a Upload dokladů:**
  - Evidence výdajů s přiřazením do kategorie.
  - Upload účtenky (PDF / JPG / PNG):
    - Validace velikosti a MIME typu.
    - Uložení pod unikátním hashem mimo veřejný přístup (nebo v zabezpečené podsložce).
    - Možnost stažení / zobrazení dokladu ověřeným uživatelem.

### Fáze 4: Dashboard OSVČ a rozhraní pro Účetního
- [x] **Dashboard OSVČ:**
  - KPI karty: Příjmy celkem, Výdaje celkem, Čistá bilance (zisk), Neuhrazené pohledávky.
  - Přehled nezaplacených faktur s termínem splatnosti.
  - Vizuální ukazatel / graf procenta uhrazení a ~~rozložení výdajů podle kategorií.~~
- [x] **Pohled Účetního:**
  - Přepínač mezi OSVČ, které účetnímu udělily přístup.
  - Read-only zobrazení faktur, plateb a výdajů dané OSVČ včetně nahraných účtenek.
  - Možnost změny kategorie výdaje (jediná povolená editace pro účetního).
  - Možnost přidat další OSVČ zadáním nového pass key.

### Fáze 5: Administrace a číselníky
- [ ] Přehled uživatelů v systému s možností blokace (`is_blocked`) a smazání.
- [ ] SuperAdmin ochrana: Pouze `superadmin` smí měnit role nebo mazat uživatele s rolí `admin`. Běžný admin smí spravovat pouze OSVČ a účetní.
- [ ] Správa číselníků: CRUD pro `expense_categories` a `client_types`.
- [ ] Přidat CSRF

### Fáze 6: Volitelná technologická rozšíření (Maximalizace bodů)
- [ ] **FullREST API endpoint (4 b):**
  - Implementovat např. `/api/clients`:
    - `GET /api/clients` – seznam klientů v JSON
    - `GET /api/clients/{id}` – detail klienta
    - `POST /api/clients` – vytvoření nového klienta
    - `PUT /api/clients/{id}` – aktualizace
    - `DELETE /api/clients/{id}` – odstranění
  - Správné HTTP stavové kódy (200, 201, 400, 401, 404).
- [ ] **WYSIWYG editor (3–4 b):**
  - Integrace Quill.js přes CDN na pole poznámky u faktur a výdajů.
- [ ] **AJAX & Bootstrap vylepšení (4 b):**
  - Asynchronní operace s feedbackem přes Bootstrap toast / alert.
  - Potvrzovací modaly před smazáním záznamů.

### Fáze 7: Finální cleanup kontrola a příprava na obhajobu
- [ ] Až bude hotová všechny backend funkcionalita, tak projít veškerý frontend a udělat jej hezčím :-)
- [ ] Projít celou appku a otestovat ... PŘIDAT LEPŠÍ CHYBOVÉ HLÁŠKY
- [ ] Validace všech HTML stránek přes W3C validátor.
- [ ] Test spuštění přes čistý `docker compose up --build`.
- [ ] Kontrola PHP chybových hlášení při zapnutém `E_ALL`.
- [ ] Příprava předváděcího scénáře pro cvičícího (před Vánocemi).
