"""Ručni e2e test signala Podsetnika (tačka / crveni broj na ikonici u headeru).

Pokretanje (razvojna aplikacija mora da radi):
    KT_URL=http://localhost:8000 KT_EMAIL=... KT_PASSWORD=... python tests/e2e/podsetnik_signal.py
Nalog je običan (ROLE_RADNIK) nalog koji ima prazan Podsetnik; skripta sama dodaje i briše svoje stavke.
Ako se verzija Playwright-a ne poklapa sa instaliranim Chromiumom, KT_CHROME je putanja do chrome.exe.
Lozinka se čita samo iz okruženja, nikad iz repoa. Van php bin/phpunit: nije obavezan test.
"""
import os
import re
import sys
from datetime import date, timedelta

from playwright.sync_api import sync_playwright

URL = os.environ.get("KT_URL", "http://localhost:8000")
EMAIL = os.environ["KT_EMAIL"]
PASSWORD = os.environ["KT_PASSWORD"]
POLL_WAIT_MS = 2500  # poll() se pokreće odmah po učitavanju; dovoljno za jedan zahtev

failures = []


def check(name, ok, extra=""):
    print(("PASS " if ok else "FAIL ") + name + (" " + extra if extra else ""))
    if not ok:
        failures.append(name)


def signal(page):
    """Šta korisnik vidi: tačka, broj."""
    return page.evaluate(
        """() => {
            const vis = (el) => !!el && !el.classList.contains('d-none') && getComputedStyle(el).display !== 'none';
            const dot = document.querySelector('.kt-todo-btn .todo-dot');
            const badge = document.querySelector('.kt-todo-btn .todo-count');
            return { dot: vis(dot), badge: vis(badge) ? badge.textContent.trim() : null };
        }"""
    )


def api(page, method, path, body=None):
    return page.evaluate(
        """async ([method, path, body]) => {
            const r = await fetch(path, { method, headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : undefined });
            return await r.json();
        }""",
        [method, path, body],
    )


def clear_items(page):
    html = api(page, "GET", "/ajax/todo")["data"]["html"]
    for a, b in re.findall(r'data-todo-item[^>]*?data-id="(\d+)"|data-id="(\d+)"[^>]*?data-todo-item', html):
        i = a or b
        api(page, "DELETE", f"/ajax/todo/{i}")


def settle(page):
    page.wait_for_timeout(POLL_WAIT_MS)


with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get("KT_CHROME") or None)
    ctx = browser.new_context(viewport={"width": 1440, "height": 900})
    page = ctx.new_page()
    page.goto(URL + "/login")
    page.fill("#username", EMAIL)
    page.fill("#password", PASSWORD)
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")

    clear_items(page)
    future = (date.today() + timedelta(days=5)).isoformat()
    yesterday = (date.today() - timedelta(days=1)).isoformat()

    # --- samo budući rok: tačka, bez broja ---
    api(page, "POST", "/ajax/todo", {"title": "E2E buduća", "dueDate": future})
    page.reload()
    settle(page)
    # dokaz: server je tačku isporučio (bez JS-a), pa je sakriva skripta u pregledaču
    served = page.evaluate("() => fetch('/').then(r => r.text()).then(h => /kt-todo-dot todo-dot (?!d-none)/.test(h))")
    check("server renderuje tačku u HTML-u", served)
    s = signal(page)
    check("F5: tačka ostaje posle poll-a (samo buduće stavke)", s["dot"] and s["badge"] is None, str(s))
    page.goto(URL + "/", wait_until="networkidle")
    settle(page)
    check("nova navigacija: tačka ostaje", signal(page)["dot"], str(signal(page)))

    # promena teme više puta
    for choice in ["dark", "light", "dark", "light"]:
        page.evaluate("c => document.querySelector(`[data-theme-choice=${c}]`).click()", choice)
        page.wait_for_timeout(400)
    settle(page)
    check("posle promene teme: tačka ostaje", signal(page)["dot"], str(signal(page)))

    # navigacija između strana i Nazad
    page.goto(URL + "/podsetnik", wait_until="networkidle")
    settle(page)
    check("druga strana: tačka ostaje", signal(page)["dot"], str(signal(page)))
    page.go_back(wait_until="networkidle")
    settle(page)
    check("dugme Nazad: tačka ostaje", signal(page)["dot"], str(signal(page)))

    # zakasnela stavka: crveni broj, tačka nestaje
    api(page, "POST", "/ajax/todo", {"title": "E2E zakasnela", "dueDate": yesterday})
    page.reload()
    settle(page)
    s = signal(page)
    check("zakasnela stavka: crveni broj 1, bez tačke", s["badge"] == "1" and not s["dot"], str(s))

    # druga kartica: vidi isto posle poll-a
    page2 = ctx.new_page()
    page2.goto(URL + "/", wait_until="networkidle")
    settle(page2)
    check("drugi tab: isti signal", signal(page2)["badge"] == "1", str(signal(page2)))
    page2.close()

    # završi zakasnelu: ostaje tačka (buduća), pa završi i nju: nema signala
    page.reload()
    settle(page)
    items = page.evaluate("() => fetch('/ajax/todo').then(r => r.json()).then(j => j.data)")
    check("odgovor /ajax/todo sadrži open", "open" in items, str({k: items[k] for k in items if k != 'html'}))
    due = page.evaluate("() => fetch('/ajax/todo/due').then(r => r.json()).then(j => j.data)")
    check("odgovor /ajax/todo/due sadrži open", "open" in due, str(due))

    clear_items(page)
    page.reload()
    settle(page)
    s = signal(page)
    check("prazan Podsetnik: nema signala", not s["dot"] and s["badge"] is None, str(s))

    browser.close()

print("\nNeuspeli:" if failures else "\nSve prošlo.", *failures, sep="\n  " if failures else "")
sys.exit(1 if failures else 0)
