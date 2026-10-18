# keep-track-enterprise

Finální zadání:

---

**Zadání semestrální práce KIV/WEB: Správa financí OSVČ**

Student: Ernst Christoph Leschka, A25B0266P, leschkae@students.zcu.cz

**1. Cíl práce**

Cílem je vytvořit webovou aplikaci pro evidenci financí OSVČ, která vychází z databázového modelu z mé semestrální práce KIV/DB1 (stejné téma). OSVČ v ní eviduje klienty, vystavuje faktury s položkami, zaznamenává platby (včetně splátek jedné faktury a jedné platby pro více faktur) a spravuje výdaje v kategoriích. Aplikace nabízí přehledy příjmů, výdajů a pohledávek. Oproti modelu z DB1 je ERA model zredukovaný a upravený tak, aby podporoval více uživatelů a rolí. Nejde o demoverzi, ale o plně funkční aplikaci.

**2. Uživatelské role**

- **Nepřihlášený uživatel** vidí úvodní stránku s popisem aplikace a může se přihlásit nebo zaregistrovat. Registrace nabízí dvě volby:
  - *Registrovat jako OSVČ*: standardní registrace (jméno, e-mail, heslo).
  - *Registrovat jako účetní pod OSVČ*: stejné údaje a navíc přístupový klíč (accountant pass key) s omezenou platností, který vygenerovala OSVČ.
- **OSVČ** spravuje (CRUD) své klienty, faktury včetně položek, platby a jejich přiřazení k fakturám a své výdaje. Platba se vždy vytváří společně s přiřazením k alespoň jedné faktuře (jeden krok, jedna transakce). Ke faktuře může nahrát PDF a k výdaji doklad. Na přehledu vidí bilanci, výdaje podle kategorií, příjmy podle klientů, nezaplacené faktury a procento uhrazení faktur. V profilu může vygenerovat přístupový klíč pro účetního a kdykoli mu přístup odebrat.
- **Účetní** vidí (read-only) data OSVČ, které mu klíčem udělily přístup, včetně přehledů a nahraných dokladů. Může upravit kategorii výdaje. Další OSVČ může přidat zadáním dalšího klíče ve svém profilu.
- **Administrátor** spravuje uživatele (blokace, smazání, povýšení na administrátora) a číselníky (kategorie výdajů, typy klientů).
- *Volitelně* **SuperAdmin**, který jako jediný spravuje ostatní administrátory (klasičtí administrátoři se navzájem upravovat nemohou).

Role admin a SuperAdmin nelze získat registrací. Každý uživatel vidí a mění jen data, na která má podle své role nárok.

**3. Přiřazení účetního (pass key)**

1. OSVČ v profilu klikne na "Pozvat účetního". Systém vygeneruje náhodný klíč (`bin2hex(random_bytes(8))`) s časově omezenou platností a uloží ho do `users.accountant_key` / `users.accountant_key_expires`.
2. OSVČ klíč předá účetnímu mimo aplikaci.
3. Účetní se zaregistruje volbou "Registrovat jako účetní pod OSVČ" a klíč vyplní. Systém ověří platnost klíče, vytvoří uživatele s rolí účetní, nastaví OSVČ `accountant_id` na nového uživatele a klíč zneplatní (jednorázový).
4. OSVČ může přístup kdykoli odebrat a vygenerovat nový klíč.

Jeden účetní tak může obsluhovat více OSVČ, každá OSVČ má nejvýše jednoho účetního.

**4. Databázový model**

Vychází z 8 tabulek z DB1 (1 odebrána): `users`, `clients`, `client_types`, `invoices`, `invoice_items`, `payments`, `invoice_payment` (vazba M:N), `expenses`. Odebrána `expense_categories`. Pro potřeby webu je rozšířen o:

- tabulku `roles` a sloupec `users.role_id`,
- `users.is_blocked` (blokace uživatele),
- `users.accountant_id` (účetní přiřazený OSVČ),
- `users.accountant_key` a `users.accountant_key_expires` (pozvánka pro účetního),

Platba (`payments`) nemá vlastní odkaz na uživatele. Protože se vždy vytváří zároveň s přiřazením k faktuře, je její vlastnictví odvoditelné přes `invoice_payment → invoices → clients → users`, čímž se model vyhýbá zbytečnému cyklu.

Databáze bude naplněna ukázkovými daty pro všechny role a instalační SQL skript bude součástí odevzdání.

**5. Technické řešení**

HTML5, CSS, PHP (OOP, MVC, jeden vstupní soubor `index.php`), MySQL přes PDO. Hesla jsou hashována Bcryptem, aplikace je chráněna proti XSS a SQL injection. Design je responzivní (PC a mobil). Framework typu Nette či Symfony nepoužiji.

**6. Plánovaná volitelná rozšíření**

Bootstrap, JavaScript/AJAX, Git, Composer, Docker, namespaces, unikátní názvy nahraných souborů, případně SuperAdmin.

---

Chceš to i jako .docx nebo .md soubor pro odevzdání, nebo ti stačí tato verze v chatu?