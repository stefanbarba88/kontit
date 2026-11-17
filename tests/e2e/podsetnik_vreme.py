"""Ručni e2e test izbora vremena u Podsetniku (SPEC 8p.6): select 24h sa korakom 30 min, vrednost van koraka se ne gubi,
brza opcija "Za 15 min" upisuje tačan minut, podsetnik iz prošlosti se okida.

Pokretanje (razvojna aplikacija mora da radi; nalog je običan ROLE_RADNIK sa praznim Podsetnikom):
    KT_URL=http://localhost:8000 KT_EMAIL=... KT_PASSWORD=... python tests/e2e/podsetnik_vreme.py
Ako se verzija Playwright-a ne poklapa sa Chromiumom, KT_CHROME je putanja do chrome.exe. Lozinka se čita samo iz okruženja.
"""
import os
import re
import sys
from datetime import date, datetime, timedelta

from playwright.sync_api import sync_playwright

URL = os.environ.get("KT_URL", "http://localhost:8000")
EMAIL = os.environ["KT_EMAIL"]
PASSWORD = os.environ["KT_PASSWORD"]
failures = []


def check(name, ok, extra=""):
    print(("PASS " if ok else "FAIL ") + name + (" " + str(extra) if extra else ""))
    if not ok:
        failures.append(name)


def api(page, method, path, body=None):
    return page.evaluate(
        """async ([method, path, body]) => {
            const r = await fetch(path, { method, headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : undefined });
            return await r.json();
        }""",
        [method, path, body],
    )


def items(page):
    html = api(page, "GET", "/ajax/todo")["data"]["html"]
    found = []
    for m in re.finditer(r'data-todo="([^"]*)"', html):
        raw = m.group(1).replace("&quot;", '"')
        field = lambda name: (re.search(r'"%s":"([^"]*)"' % name, raw) or [None, ""])[1]
        found.append({"title": field("title"), "dueTime": field("dueTime"), "remindTime": field("remindTime"), "id": (re.search(r'"id":(\d+)', raw) or [None, 0])[1]})
    return found


def clear(page):
    for i in items(page):
        api(page, "DELETE", f"/ajax/todo/{i['id']}")


def open_add(page):
    page.locator(".kt-todo-card [data-todo-new]").first.click()
    page.wait_for_selector("#kt-td-due-time")


def options(page, sel):
    return page.evaluate("s => [...document.querySelector(s).options].map(o => o.value)", sel)


with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get("KT_CHROME") or None)
    ctx = browser.new_context(viewport={"width": 1440, "height": 900})
    page = ctx.new_page()
    page.set_default_timeout(90000)  # razvojni server je spor pri prvom učitavanju asseta
    page.goto(URL + "/login")
    page.fill("#username", EMAIL)
    page.fill("#password", PASSWORD)
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")
    clear(page)
    page.goto(URL + "/", wait_until="networkidle")

    # 1) komponenta: select, 48 vremena + prazna opcija, nikad type=time
    open_add(page)
    due = options(page, "#kt-td-due-time")
    check("rok: select sa 48 vremena + prazna opcija", len(due) == 49 and due[0] == "" and due[1] == "00:00" and due[-1] == "23:30", (len(due), due[:3]))
    check("nema nativnog type=time", page.locator("input[type=time]").count() == 0)
    check("sva vremena su na pola sata", all(t.endswith(":00") or t.endswith(":30") for t in due[1:]))
    page.click(".swal2-cancel")
    page.wait_for_timeout(400)

    # 2) izbor 14:30 i snimanje
    open_add(page)
    page.fill("#kt-td-title", "E2E vreme")
    page.click("[data-chip-due]")  # Danas
    page.select_option("#kt-td-due-time", "14:30")
    page.click(".swal2-confirm")
    page.wait_for_timeout(1200)
    saved = [i for i in items(page) if i["title"] == "E2E vreme"]
    check("snimljen rok 14:30", len(saved) == 1 and saved[0]["dueTime"] == "14:30", saved and saved[0]["dueTime"])
    page.reload(wait_until="networkidle")
    page.locator(".kt-todo-card .kt-todo-title", has_text="E2E vreme").click()
    page.wait_for_selector("#kt-td-due-time")
    check("izmena prikazuje 14:30", page.input_value("#kt-td-due-time") == "14:30")
    page.click(".swal2-cancel")
    page.wait_for_timeout(400)

    # 3) vrednost van koraka (14:15) se ne gubi i ne zaokružuje
    today = date.today().isoformat()
    api(page, "POST", "/ajax/todo", {"title": "E2E van koraka", "dueDate": today, "dueTime": "14:15"})
    page.reload(wait_until="networkidle")
    page.locator(".kt-todo-card .kt-todo-title", has_text="E2E van koraka").click()
    page.wait_for_selector("#kt-td-due-time")
    opts = options(page, "#kt-td-due-time")
    check("14:15 je dodatna izabrana opcija", page.input_value("#kt-td-due-time") == "14:15" and len(opts) == 50 and opts.index("14:15") == opts.index("14:00") + 1, (len(opts), page.input_value("#kt-td-due-time")))
    page.click(".swal2-confirm")  # potvrda bez izmene vremena
    page.wait_for_timeout(1200)
    still = [i for i in items(page) if i["title"] == "E2E van koraka"]
    check("posle snimanja bez izmene i dalje 14:15", len(still) == 1 and still[0]["dueTime"] == "14:15", still and still[0]["dueTime"])

    # 4) "Za 15 min": tačan minut, select prikazuje dodatnu opciju
    open_add(page)
    page.fill("#kt-td-title", "E2E za 15")
    page.click("[data-td-more]")
    page.click("[data-chip-in='15']")
    got = page.input_value("#kt-td-remind-time")
    expected = datetime.now() + timedelta(minutes=15)
    h, m = map(int, got.split(":"))
    diff = abs((h * 60 + m) - (expected.hour * 60 + expected.minute))
    check("Za 15 min: vreme je tačan minut (oko +15)", diff <= 2 or diff >= 1438, got)
    check("Za 15 min: opcija postoji u selectu", got in options(page, "#kt-td-remind-time"))
    page.click(".swal2-confirm")
    page.wait_for_timeout(1200)
    rel = [i for i in items(page) if i["title"] == "E2E za 15"]
    check("Za 15 min: snimljeno tačno vreme", len(rel) == 1 and rel[0]["remindTime"] == got, rel and rel[0]["remindTime"])

    # 5) 390 px i tamna tema: select se vidi i ima 48 vremena
    page.set_viewport_size({"width": 390, "height": 800})
    page.goto(URL + "/", wait_until="networkidle")
    page.evaluate("() => document.querySelector('[data-theme-choice=dark]').click()")
    page.wait_for_timeout(500)
    open_add(page)
    check("390 px, tamna tema: select vidljiv", page.locator("#kt-td-due-time").is_visible() and len(options(page, "#kt-td-due-time")) == 49)
    page.click(".swal2-cancel")
    page.evaluate("() => document.querySelector('[data-theme-choice=light]').click()")
    page.set_viewport_size({"width": 1440, "height": 900})

    # 6) podsetnik iz prošlosti se i dalje okida (poll ga prikazuje kao obaveštenje)
    clear(page)
    api(page, "POST", "/ajax/todo", {"title": "E2E okida se", "remindDate": today, "remindTime": "00:00"})
    page.goto(URL + "/", wait_until="networkidle")
    page.wait_for_timeout(3000)
    check("podsetnik iz prošlosti se okida", page.locator("text=E2E okida se").count() > 0)

    clear(page)
    browser.close()

print("\nNeuspeli:" if failures else "\nSve prošlo.", *failures, sep="\n  " if failures else "")
sys.exit(1 if failures else 0)
