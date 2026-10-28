# FullCalendar 6.1.22 (Standard Bundle, MIT)

Originalni fajl `https://cdn.jsdelivr.net/npm/fullcalendar@6.1.22/index.global.min.js`, neizmenjen.
Sadrži core, daygrid, timegrid, list, interaction i multimonth (sve MIT; premium paketi se ne koriste).

Zašto nije u importmap-u: jsDelivr `+esm` pakuje svaki ulaz `@fullcalendar/*` posebno, pa plugin-ovi dobijaju drugu kopiju
klasa jezgra i kalendar puca ("Class constructor ... cannot be invoked without 'new'"), a njihova verzija standardnog paketa
izbacuje `var FullCalendar =`. Zato se ovaj fajl učitava kao obična skripta samo na stranici kalendara (`templates/calendar/index.html.twig`).
Nadogradnja: zameniti fajl novom verzijom istog paketa i promeniti ime foldera.
