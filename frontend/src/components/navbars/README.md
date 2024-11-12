TIPOLOGIE DI NAVBAR:
----------------------

navbar_large_1_menu_items_collapse:
..............

Navbar usata in milanosport.
Navbar orizzontale su media large e hamburger sotto il large.
Hamburger ad apertura che spinge tutti i contenuti sotto

Voci menu con collapse animato sull'altezza.


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


navbar_large_2.xsl
..............

Navbar presa da adsi (con menu area riservata) - Da completare. Manca scss, c'è solo xsl.


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


nav_grow_top.xsl
..............

Animazione del menu che cresce verso la parte superiore della finestra mentre l'animazione del colore della navbar va nella direzione opposta.


Contiene 2 navbar e 2 menu collapse con la possibilità di attivare il carrello per i progetti di e-commerce:

1. Navbar toggler: basato su Allianz Cloud, questo modello ha il menu toggler sempre attivo. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
2. Navbar extra large: basato su Milanosport, questo modello ha il menu toggler attivo nelle media sotto extra large (xl). Dopo questo punto, presenta una riga di voci di menu. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
3. Collapse (basic): basato su Allianz Cloud, questo modello presenta un menu semplice con voci di menu
4. Collapse (sections): basato su quello di DotStay, questo modello presenta un design più robusto e dinamico, con più elementi, compreso anche un footer integrato per la sponsorizzazione e il contatto con l'azienda
5. Carrello (Cart): basato su DotStay, per progetti di e-commerce.

Contiene anche 2 <div> vuoti la cui funzione è quella di attivare l'animazione di scorrimento della barra di navigazione e del carrello mentre si aprono e si chiudono.


Per modificare la larghezza, cambia la `.collapse_contents` con le classi `.col-*`.


Per attivare slot extra:

1. Decommenta `.box_center` alla riga 30
2. Commenta `.box_center` alla riga 39
3. Decommenta `.box_center` alla riga 47.


Per progetti di e-commerce:

1. Decommenta il <button> con la classe `.menu_steps`
2. Decommenta il <div> con la classe `.coll_ghost_bg`


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


nav_grow_right.xsl
..............

Animazione del menu che cresce verso la destra della finestra mentre l'animazione del colore della navbar va nella direzione opposta.


Contiene 2 navbar e 2 menu collapse con la possibilità di attivare il carrello per i progetti di e-commerce:

1. Navbar toggler: basato su Allianz Cloud, questo modello ha il menu toggler sempre attivo. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
2. Navbar extra large: basato su Milanosport, questo modello ha il menu toggler attivo nelle media sotto extra large (xl). Dopo questo punto, presenta una riga di voci di menu. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
3. Collapse (basic): basato su Allianz Cloud, questo modello presenta un menu semplice con voci di menu
4. Collapse (sections): basato su quello di DotStay, questo modello presenta un design più robusto e dinamico, con più elementi, compreso anche un footer integrato per la sponsorizzazione e il contatto con l'azienda
5. Carrello (Cart): basato su DotStay, per progetti di e-commerce.

Contiene anche 2 <div> vuoti la cui funzione è quella di attivare l'animazione di scorrimento della barra di navigazione e del carrello mentre si aprono e si chiudono.


Per modificare la larghezza, cambia la `.collapse_contents` con le classi `.col-*`.


Per attivare slot extra:

1. Decommenta `.box_center` alla riga 30
2. Commenta `.box_center` alla riga 39
3. Decommenta `.box_center` alla riga 47.


Per progetti di e-commerce:

1. Decommenta il <button> con la classe `.menu_steps`
2. Decommenta il <div> con la classe `.coll_ghost_bg`
3. Nel file SCSS corrispondente, cambia la variabile `var(--nav_bg_color_right)` in `var(--nav_bg_color_left)` nella classe `.nav_ghost_bg` (riga 24) per cambiare la direzione dell'animazione del colore della navbar.


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


nav_grow_bottom.xsl
..............

Animazione del menu che cresce verso la parte inferiore della finestra mentre l'animazione del colore della navbar va nella direzione opposta.


Contiene 2 navbar e 2 menu collapse con la possibilità di attivare il carrello per i progetti di e-commerce:

1. Navbar toggler: basato su Allianz Cloud, questo modello ha il menu toggler sempre attivo. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
2. Navbar extra large: basato su Milanosport, questo modello ha il menu toggler attivo nelle media sotto extra large (xl). Dopo questo punto, presenta una riga di voci di menu. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
3. Collapse (basic): basato su Allianz Cloud, questo modello presenta un menu semplice con voci di menu
4. Collapse (sections): basato su quello di DotStay, questo modello presenta un design più robusto e dinamico, con più elementi, compreso anche un footer integrato per la sponsorizzazione e il contatto con l'azienda
5. Carrello (Cart): basato su DotStay, per progetti di e-commerce.

Contiene anche 2 <div> vuoti la cui funzione è quella di attivare l'animazione di scorrimento della barra di navigazione e del carrello mentre si aprono e si chiudono.


Per modificare la larghezza, cambia la `.collapse_contents` con le classi `.col-*`.


Per attivare slot extra:

1. Decommenta `.box_center` alla riga 30
2. Commenta `.box_center` alla riga 39
3. Decommenta `.box_center` alla riga 47.


Per progetti di e-commerce:

1. Decommenta il <button> con la classe `.menu_steps`
2. Decommenta il <div> con la classe `.coll_ghost_bg`


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


nav_grow_left.xsl
..............

Animazione del menu che cresce verso la sinistra della finestra mentre l'animazione del colore della navbar va nella direzione opposta.


Contiene 2 navbar e 2 menu collapse con la possibilità di attivare il carrello per i progetti di e-commerce:

1. Navbar toggler: basato su Allianz Cloud, questo modello ha il menu toggler sempre attivo. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
2. Navbar extra large: basato su Milanosport, questo modello ha il menu toggler attivo nelle media sotto extra large (xl). Dopo questo punto, presenta una riga di voci di menu. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
3. Collapse (basic): basato su Allianz Cloud, questo modello presenta un menu semplice con voci di menu
4. Collapse (sections): basato su quello di DotStay, questo modello presenta un design più robusto e dinamico, con più elementi, compreso anche un footer integrato per la sponsorizzazione e il contatto con l'azienda
5. Carrello (Cart): basato su DotStay, per progetti di e-commerce.

Contiene anche 2 <div> vuoti la cui funzione è quella di attivare l'animazione di scorrimento della barra di navigazione e del carrello mentre si aprono e si chiudono.


Per modificare la larghezza, cambia la `.collapse_contents` con le classi `.col-*`.


Per attivare slot extra:

1. Decommenta `.box_center` alla riga 30
2. Commenta `.box_center` alla riga 39
3. Decommenta `.box_center` alla riga 47.


Per progetti di e-commerce:

1. Decommenta il <button> con la classe `.menu_steps`
2. Decommenta il <div> con la classe `.coll_ghost_bg`
3. Nel file SCSS corrispondente, cambia la variabile `var(--nav_bg_color_left)` in `var(--nav_bg_color_right)` nella classe `.nav_ghost_bg` (riga 24) per cambiare la direzione dell'animazione del colore della navbar.


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


nav_slide_top.xsl
..............

Animazione dello scorrimento del menu verso la parte superiore della finestra mentre l'animazione del colore della navbar va nella direzione opposta.


Contiene 2 navbar e 2 menu collapse con la possibilità di attivare il carrello per i progetti di e-commerce:

1. Navbar toggler: basato su Allianz Cloud, questo modello ha il menu toggler sempre attivo. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
2. Navbar extra large: basato su Milanosport, questo modello ha il menu toggler attivo nelle media sotto extra large (xl). Dopo questo punto, presenta una riga di voci di menu. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
3. Collapse (basic): basato su Allianz Cloud, questo modello presenta un menu semplice con voci di menu
4. Collapse (sections): basato su quello di DotStay, questo modello presenta un design più robusto e dinamico, con più elementi, compreso anche un footer integrato per la sponsorizzazione e il contatto con l'azienda
5. Carrello (Cart): basato su DotStay, per progetti di e-commerce.

Contiene anche 2 <div> vuoti la cui funzione è quella di attivare l'animazione di scorrimento della barra di navigazione e del carrello mentre si aprono e si chiudono.


Per modificare la larghezza, cambia la `.collapse_contents` con le classi `.col-*`.


Per attivare slot extra:

1. Decommenta `.box_center` alla riga 30
2. Commenta `.box_center` alla riga 39
3. Decommenta `.box_center` alla riga 47.


Per progetti di e-commerce:

1. Decommenta il <button> con la classe `.menu_steps`
2. Decommenta il <div> con la classe `.coll_ghost_bg`


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


nav_slide_right.xsl
..............

Animazione dello scorrimento del menu verso la destra della finestra mentre l'animazione del colore della navbar va nella direzione opposta.


Contiene 2 navbar e 2 menu collapse con la possibilità di attivare il carrello per i progetti di e-commerce:

1. Navbar toggler: basato su Allianz Cloud, questo modello ha il menu toggler sempre attivo. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
2. Navbar extra large: basato su Milanosport, questo modello ha il menu toggler attivo nelle media sotto extra large (xl). Dopo questo punto, presenta una riga di voci di menu. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
3. Collapse (basic): basato su Allianz Cloud, questo modello presenta un menu semplice con voci di menu
4. Collapse (sections): basato su quello di DotStay, questo modello presenta un design più robusto e dinamico, con più elementi, compreso anche un footer integrato per la sponsorizzazione e il contatto con l'azienda
5. Carrello (Cart): basato su DotStay, per progetti di e-commerce.

Contiene anche 2 <div> vuoti la cui funzione è quella di attivare l'animazione di scorrimento della barra di navigazione e del carrello mentre si aprono e si chiudono.


Per modificare la larghezza, cambia la `.collapse_contents` con le classi `.col-*`.


Per attivare slot extra:

1. Decommenta `.box_center` alla riga 30
2. Commenta `.box_center` alla riga 39
3. Decommenta `.box_center` alla riga 47.


Per progetti di e-commerce:

1. Decommenta il <button> con la classe `.menu_steps`
2. Decommenta il <div> con la classe `.coll_ghost_bg`
3. Nel file SCSS corrispondente, cambia la variabile `var(--nav_bg_color_right)` in `var(--nav_bg_color_left)` nella classe `.nav_ghost_bg` (riga 24) per cambiare la direzione dell'animazione del colore della navbar.


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


nav_slide_bottom.xsl
..............

Animazione dello scorrimento del menu verso la parte inferiore della finestra mentre l'animazione del colore della navbar va nella direzione opposta.


Contiene 2 navbar e 2 menu collapse con la possibilità di attivare il carrello per i progetti di e-commerce:

1. Navbar toggler: basato su Allianz Cloud, questo modello ha il menu toggler sempre attivo. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
2. Navbar extra large: basato su Milanosport, questo modello ha il menu toggler attivo nelle media sotto extra large (xl). Dopo questo punto, presenta una riga di voci di menu. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
3. Collapse (basic): basato su Allianz Cloud, questo modello presenta un menu semplice con voci di menu
4. Collapse (sections): basato su quello di DotStay, questo modello presenta un design più robusto e dinamico, con più elementi, compreso anche un footer integrato per la sponsorizzazione e il contatto con l'azienda
5. Carrello (Cart): basato su DotStay, per progetti di e-commerce.

Contiene anche 2 <div> vuoti la cui funzione è quella di attivare l'animazione di scorrimento della barra di navigazione e del carrello mentre si aprono e si chiudono.


Per modificare la larghezza, cambia la `.collapse_contents` con le classi `.col-*`.


Per attivare slot extra:

1. Decommenta `.box_center` alla riga 30
2. Commenta `.box_center` alla riga 39
3. Decommenta `.box_center` alla riga 47.


Per progetti di e-commerce:

1. Decommenta il <button> con la classe `.menu_steps`
2. Decommenta il <div> con la classe `.coll_ghost_bg`


XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX


nav_slide_left.xsl
..............

Animazione dello scorrimento del menu verso la sinistra della finestra mentre l'animazione del colore della navbar va nella direzione opposta.


Contiene 2 navbar e 2 menu collapse con la possibilità di attivare il carrello per i progetti di e-commerce:

1. Navbar toggler: basato su Allianz Cloud, questo modello ha il menu toggler sempre attivo. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
2. Navbar extra large: basato su Milanosport, questo modello ha il menu toggler attivo nelle media sotto extra large (xl). Dopo questo punto, presenta una riga di voci di menu. Include l'opzione di uno slot aggiuntivo (`.nav_extra`). Nascondere elemento con `.d-none` quando non è in uso
3. Collapse (basic): basato su Allianz Cloud, questo modello presenta un menu semplice con voci di menu
4. Collapse (sections): basato su quello di DotStay, questo modello presenta un design più robusto e dinamico, con più elementi, compreso anche un footer integrato per la sponsorizzazione e il contatto con l'azienda
5. Carrello (Cart): basato su DotStay, per progetti di e-commerce.

Contiene anche 2 <div> vuoti la cui funzione è quella di attivare l'animazione di scorrimento della barra di navigazione e del carrello mentre si aprono e si chiudono.


Per modificare la larghezza, cambia la `.collapse_contents` con le classi `.col-*`.


Per attivare slot extra:

1. Decommenta `.box_center` alla riga 30
2. Commenta `.box_center` alla riga 39
3. Decommenta `.box_center` alla riga 47.


Per progetti di e-commerce:

1. Decommenta il <button> con la classe `.menu_steps`
2. Decommenta il <div> con la classe `.coll_ghost_bg`
3. Nel file SCSS corrispondente, cambia la variabile `var(--nav_bg_color_left)` in `var(--nav_bg_color_right)` nella classe `.nav_ghost_bg` (riga 24) per cambiare la direzione dell'animazione del colore della navbar.



Completare con le altre navbar mantenendo questa formattazione del testo.