# KeepTrack Enterprise

Semestrální práce z předmětu **KIV/WEB** (Západočeská univerzita v Plzni) — webová aplikace pro správu financí OSVČ.

- **Autor:** Ernst Christoph Leschka (`A25B0266P`, `leschkae@students.zcu.cz`)
- **Cvičící:** Michal Nykl

---

## 1. Cíl a popis aplikace

Aplikace umožňuje osobám samostatně výdělečně činným (OSVČ) evidovat klienty, vystavovat faktury s jednotlivými položkami, evidovat platby (včetně párování jedné platby na více faktur i postupných splátek jedné faktury) a zaznamenávat výdaje včetně přikládání účtenek. Datový model vychází ze semestrální práce **KIV/DB1** a je rozšířen o správu uživatelů, rolí, blokací a jednorázových pozvánek pro účetní.

---

## 2. Uživatelské role a oprávnění

- **Nepřihlášený uživatel (`anon`):** prohlíží veřejné stránky (`/`, `/about`, `/features`), může se přihlásit nebo zaregistrovat:
  - *Registrace jako OSVČ:* standardní účet (uživatelské jméno, e-mail, profese, heslo).
  - *Registrace jako účetní:* vyžaduje platný jednorázový klíč (`accountant_key`) vygenerovaný OSVČ.
- **OSVČ (`osvc`):** plná CRUD správa vlastních klientů, faktur a jejich položek (v DB transakci), plateb s přiřazením k fakturám (v DB transakci s automatickým označením uhrazených faktur) a výdajů včetně nahrání/stažení účtenky. Na dashboardu vidí přehled příjmů, výdajů, neuhrazených pohledávek a procento uhrazení faktur. V profilu generuje pozvánkový klíč pro účetního a může mu přístup odebrat.
- **Účetní (`ucetni`):** může obsluhovat více OSVČ (další OSVČ připojí zadáním klíče ve svém profilu nebo přes sekci klientů), přepíná mezi nimi a v režimu *read-only* prohlíží jejich přehledy, faktury, platby, výdaje i nahrané doklady.
- **Administrátor (`admin`):** zobrazuje systémový přehled, spravuje běžné uživatele (`osvc`, `ucetni` – blokace, změna role mimo povýšení na admin/superadmin, mazání) a spravuje číselník typů klientů (`client_types`).
- **SuperAdmin (`superadmin`):** má plná práva nad správou uživatelů včetně administrátorů („neovlivnitelnost adminů“ – běžní administrátoři nemohou upravovat ani mazat jiné administrátory ani superadmina).

Role `admin` a `superadmin` nelze získat přes veřejnou registraci. Každý uživatel má přístup pouze k datům odpovídajícím jeho roli a vlastnictví.

---

## 3. Přiřazení účetního (Pass Key)

1. OSVČ ve svém profilu (`/profile`) vygeneruje náhodný klíč (`bin2hex(random_bytes(8))`) s časovou platností (24 hodin), který se uloží do `users.accountant_key` a `users.accountant_key_expires`.
2. Klíč předá účetnímu mimo aplikaci.
3. Účetní se buď zaregistruje na `/register?mode=accountant`, nebo již existující účetní zadá klíč v rozhraní účetního (`/accountant/clients`).
4. Systém ověří platnost a expiraci klíče, nastaví `users.accountant_id` u dané OSVČ a klíč zneplatní.
5. OSVČ může přístup účetního kdykoli odebrat v profilu.

---

## 4. Databázový model

Schéma obsahuje **9 tabulek** (v [`sql/seed.sql`](sql/seed.sql)):

- `roles` – uživatelské role (`superadmin`, `admin`, `osvc`, `ucetni`)
- `users` – účty uživatelů, hash hesla, `role_id`, `is_blocked`, `accountant_id`, `accountant_key`, `accountant_key_expires`
- `client_types` – číselník typů klientů (`Fyzická osoba`, `Firma`, `OSVČ`, …)
- `clients` – klienti přiřazení ke konkrétní OSVČ (`user_id`, `type_id`, IČO, DIČ, kontakty)
- `invoices` – hlavičky faktur (`client_id`, číslo faktury, splatnost, celková částka, stav `paid`)
- `invoice_items` – položky faktur (`invoice_id`, název, množství, jednotková cena)
- `payments` – přijaté platby (`specific_symbol`, částka, datum)
- `invoice_payment` – vazba M:N mezi fakturami a platbami (`amount_assigned`)
- `expenses` – výdaje OSVČ včetně metadat nahrané účtenky (`receipt_filename`, `receipt_original_name`, `receipt_mime`)

Vlastnictví platby je odvoditelné přes `invoice_payment → invoices → clients → users` bez redundantních vazeb.

---

## 5. Technické řešení a bezpečnost

- **Architektura:** Vlastní objektové **MVC** v PHP 8.2 (`declare(strict_types=1)`), PSR-4 autoloading pod jmenným prostorem `Src\`, jednotný vstupní bod [`public/index.php`](public/index.php) a vlastní [`Src\Core\Router`](src/Core/Router.php).
- **Šablony a UI:** **Twig 3** (dědičnost přes `layout.twig`, partials pro hlavičku, patičku a modály), **Bootstrap 6** + **Bootstrap Icons**, responzivní mobilní drawer menu a card-stack tabulky (`public/assets/css/table.css`).
- **Interaktivita:** Vanilla JavaScript + **AJAX (`fetch`)** pro modální načítání, vytváření, úpravy a mazání záznamů bez zbytečného reloadu, dynamické přidávání položek faktur a platební párování, ochrana proti dvojitému odeslání formulářů.
- **Bezpečnost:**
  - Hesla hashována pomocí `password_hash(..., PASSWORD_BCRYPT)`.
  - Všechny SQL dotazy se vstupy od uživatele přes **PDO prepared statements** (ochrana proti SQL Injection).
  - Výchozí Twig auto-escaping (ochrana proti XSS).
  - Globální **CSRF** ochrana všech `POST` požadavků přes [`Src\Middleware\CSRF`](src/Middleware/CSRF.php).
  - Upload účtenek (JPG, PNG, WEBP, PDF, max. 5 MB) validován přes `finfo` MIME kontrolu, ukládán pod kryptograficky unikátním názvem (`bin2hex(random_bytes(32))`) mimo veřejný adresář `public/` a vydáván pouze oprávněnému vlastníkovi či přiřazenému účetnímu.
- **Validace:** Všechny veřejné i autentizované pohledy procházejí W3C/Nu HTML5 validací s 0 chybami.

---

## 6. Spuštění projektu (Docker)

```bash
docker compose up --build -d
```

Docker při prvním spuštění automaticky:
1. Sestaví PHP 8.2 Apache image a nainstaluje Composer závislosti (`twig/twig`).
2. Inicializuje MariaDB databázi a importuje schéma i ukázková data z [`sql/seed.sql`](sql/seed.sql).
3. Spustí služby:
   - **Aplikace:** <http://localhost:8080>
   - **phpMyAdmin:** <http://localhost:8081>

### Testovací účty (heslo pro všechny: `asdfasdf`)

| Role | E-mail | Popis |
| :--- | :--- | :--- |
| **SuperAdmin** | `superadmin@kte.cz` | Plná správa uživatelů včetně adminů a číselníku typů klientů |
| **Admin** | `admin@kte.cz` | Správa OSVČ/účetních a číselníku typů klientů |
| **OSVČ** | `user@kte.cz` | Demo účet s předvyplněnými klienty, fakturami, platbami a výdaji |
| **Účetní** | `accountant@kte.cz` | Propojený s demo OSVČ účtem |
