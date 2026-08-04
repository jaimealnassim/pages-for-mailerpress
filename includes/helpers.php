<?php
defined('ABSPATH') || exit;

/**
 * Default settings for the plugin.
 */
function pfmp_defaults() {
    return array(
        'enabled'          => 1,
        'layout'           => 'centered',
        'offramp'          => 1,
        'takeover'         => 0,
        'logo_id'          => 0,
        'logo_url'         => '',
        'pub_name'         => get_bloginfo('name'),
        'tagline'          => '',
        'preset'           => 'meadow',
        'palette'          => array(
            'accent'    => '#14392E',
            'overrides' => array(
                'background' => '',
                'surface'    => '',
                'text'       => '',
                'muted'      => '',
                'border'     => '',
            ),
        ),
        'lang_switcher'    => 1,
        'languages'        => array('en', 'ar', 'es'),
        'default_lang'     => 'locale',
        'allow_url_lang'   => 1,
        'assets_scoped'    => 1,
        'custom_css'       => '',
        'uninstall_cleanup'=> 0,
        'style'            => array(
            'radius' => 18,
            'shadow' => 'soft',
            'background_image' => array(
                'id'     => 0,
                'url'    => '',
                'repeat' => 0,
                'cover'  => 1,
            ),
        ),
    );
}

/**
 * Named shadow presets for the card. Kept intentionally small; "none" and
 * "soft" cover most uses, "strong" is for full-page mode against a busy
 * background image.
 */
function pfmp_shadow_presets() {
    return array(
        'none'   => array('label' => __('None', 'pages-for-mailerpress'),   'css' => 'none'),
        'soft'   => array('label' => __('Soft', 'pages-for-mailerpress'),   'css' => '0 1px 2px rgba(20,20,20,.04), 0 18px 48px -24px rgba(20,20,20,.22)'),
        'strong' => array('label' => __('Strong', 'pages-for-mailerpress'), 'css' => '0 30px 70px -12px rgba(15,23,42,.35)'),
    );
}

/**
 * First character of a string, safe on hosts without mbstring.
 */
function pfmp_first_char($str, $fallback = 'M') {
    $str = trim((string) $str);
    if ($str === '') {
        return $fallback;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($str, 0, 1);
    }
    return substr($str, 0, 1);
}

/**
 * Merged settings (saved values over defaults).
 */
function pfmp_get_settings() {
    $saved = get_option(PFMP_OPTION, array());
    if (!is_array($saved)) {
        $saved = array();
    }
    $settings = wp_parse_args($saved, pfmp_defaults());

    // wp_parse_args is shallow; make sure a partially-saved palette still has
    // every override key so callers never hit an undefined index.
    $defaults_palette = pfmp_defaults()['palette'];
    $saved_palette     = is_array($saved['palette'] ?? null) ? $saved['palette'] : array();
    $settings['palette'] = wp_parse_args($saved_palette, $defaults_palette);
    $settings['palette']['overrides'] = wp_parse_args(
        is_array($saved_palette['overrides'] ?? null) ? $saved_palette['overrides'] : array(),
        $defaults_palette['overrides']
    );

    // Same shallow-merge problem for 'style' and its nested background_image.
    $defaults_style = pfmp_defaults()['style'];
    $saved_style     = is_array($saved['style'] ?? null) ? $saved['style'] : array();
    $settings['style'] = wp_parse_args($saved_style, $defaults_style);
    $settings['style']['background_image'] = wp_parse_args(
        is_array($saved_style['background_image'] ?? null) ? $saved_style['background_image'] : array(),
        $defaults_style['background_image']
    );

    return $settings;
}

/**
 * The 30 languages this plugin ships, with autonyms and text direction.
 * The key is an ISO 639-1 code; 'en' is the untranslated source language.
 */
function pfmp_supported_languages() {
    return array(
        'en' => array('label' => 'English',    'native' => 'English',          'rtl' => false),
        'es' => array('label' => 'Spanish',    'native' => 'Español',          'rtl' => false),
        'ar' => array('label' => 'Arabic',     'native' => 'العربية',          'rtl' => true),
        'fr' => array('label' => 'French',     'native' => 'Français',         'rtl' => false),
        'de' => array('label' => 'German',     'native' => 'Deutsch',          'rtl' => false),
        'pt' => array('label' => 'Portuguese', 'native' => 'Português',        'rtl' => false),
        'it' => array('label' => 'Italian',    'native' => 'Italiano',         'rtl' => false),
        'nl' => array('label' => 'Dutch',      'native' => 'Nederlands',       'rtl' => false),
        'ru' => array('label' => 'Russian',    'native' => 'Русский',          'rtl' => false),
        'pl' => array('label' => 'Polish',     'native' => 'Polski',           'rtl' => false),
        'tr' => array('label' => 'Turkish',    'native' => 'Türkçe',           'rtl' => false),
        'sv' => array('label' => 'Swedish',    'native' => 'Svenska',          'rtl' => false),
        'da' => array('label' => 'Danish',     'native' => 'Dansk',            'rtl' => false),
        'nb' => array('label' => 'Norwegian',  'native' => 'Norsk Bokmål',     'rtl' => false),
        'fi' => array('label' => 'Finnish',    'native' => 'Suomi',            'rtl' => false),
        'cs' => array('label' => 'Czech',      'native' => 'Čeština',          'rtl' => false),
        'el' => array('label' => 'Greek',      'native' => 'Ελληνικά',         'rtl' => false),
        'he' => array('label' => 'Hebrew',     'native' => 'עברית',            'rtl' => true),
        'hi' => array('label' => 'Hindi',      'native' => 'हिन्दी',            'rtl' => false),
        'ja' => array('label' => 'Japanese',   'native' => '日本語',            'rtl' => false),
        'ko' => array('label' => 'Korean',     'native' => '한국어',            'rtl' => false),
        'zh' => array('label' => 'Chinese',    'native' => '简体中文',          'rtl' => false),
        'th' => array('label' => 'Thai',       'native' => 'ไทย',              'rtl' => false),
        'vi' => array('label' => 'Vietnamese', 'native' => 'Tiếng Việt',       'rtl' => false),
        'id' => array('label' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'rtl' => false),
        'uk' => array('label' => 'Ukrainian',  'native' => 'Українська',       'rtl' => false),
        'ro' => array('label' => 'Romanian',   'native' => 'Română',           'rtl' => false),
        'hu' => array('label' => 'Hungarian',  'native' => 'Magyar',           'rtl' => false),
        'fa' => array('label' => 'Persian',    'native' => 'فارسی',            'rtl' => true),
        'bg' => array('label' => 'Bulgarian',  'native' => 'Български',         'rtl' => false),
    );
}

/**
 * Page IDs MailerPress uses for its manage and unsubscribe pages, when the
 * admin has picked a custom WordPress Page for them (used only to list
 * "Pages in use" and to recognize a custom page by ID). This does NOT cover
 * MailerPress's own default page (see pfmp_url_for_action() for that);
 * MailerPress auto-creates a hidden page of its own when no custom page is
 * chosen, so "nothing here" doesn't mean "nothing configured".
 *
 * @return array{manage:int,unsubscribe:int} 0 for a role using the default.
 */
function pfmp_mailerpress_custom_pages() {
    $pages = array('manage' => 0, 'unsubscribe' => 0);
    $raw   = get_option('mailerpress_default_settings');
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (is_array($raw)) {
        if (empty($raw['subpage']['useDefault']) && !empty($raw['subpage']['pageId'])) {
            $pages['manage'] = (int) $raw['subpage']['pageId'];
        }
        if (empty($raw['unsubpage']['useDefault']) && !empty($raw['unsubpage']['pageId'])) {
            $pages['unsubscribe'] = (int) $raw['unsubpage']['pageId'];
        }
    }
    return $pages;
}

/**
 * Custom MailerPress page IDs, deduplicated, for the "Pages in use" list and
 * for recognizing a custom page by post ID.
 *
 * @return int[]
 */
function pfmp_mailerpress_page_ids() {
    return array_values(array_filter(array_unique(pfmp_mailerpress_custom_pages())));
}

/**
 * Which page role (manage or unsubscribe) shows a given ?action= state.
 * The manage page handles the subscribe lifecycle (the form itself, and the
 * "subscription confirmed" double opt-in state); the unsubscribe page
 * handles the unsubscribe confirm/success states.
 */
function pfmp_page_role_for_action($action) {
    return in_array($action, array('confirm_unsubscribe', 'unsubscribe'), true) ? 'unsubscribe' : 'manage';
}

/**
 * URL for a given ?action= state, resolved through MailerPress's own
 * mailerpress_get_page() function rather than re-deriving it ourselves.
 * That function already knows exactly where the admin has their pages,
 * whether that's a custom WordPress Page or MailerPress's own default page
 * (auto-created on activation, with no dedicated Page needed) so this stays
 * correct wherever the admin actually has things configured, including
 * sites that never touched MailerPress's page settings at all.
 *
 * mailerpress_get_page() always returns its own action baked into the URL
 * ('manage' for the manage page, 'confirm_unsubscribe' for the unsubscribe
 * page); we swap in the action we actually want to preview.
 */
function pfmp_url_for_action($action) {
    if (!function_exists('mailerpress_get_page')) {
        return '';
    }
    $role    = pfmp_page_role_for_action($action);
    $context = ($role === 'unsubscribe') ? 'unsub_page' : 'manage_page';
    $base    = mailerpress_get_page($context);
    if (empty($base)) {
        return '';
    }
    $base = remove_query_arg('action', $base);
    return add_query_arg('action', sanitize_key($action), $base);
}

/**
 * Is the current request a MailerPress subscription/unsubscribe page? This
 * covers both a custom WordPress Page (matched by shortcode or by the ID
 * MailerPress has configured) and MailerPress's own default page (a hidden
 * post type whose content is the shortcode itself, so has_shortcode()
 * already catches it).
 */
function pfmp_is_mailerpress_page() {
    if (is_admin() || !is_singular()) {
        return false;
    }
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return false;
    }
    if (has_shortcode((string) $post->post_content, 'mailerpress_pages')) {
        return true;
    }
    if (in_array($post->ID, pfmp_mailerpress_page_ids(), true)) {
        return true;
    }
    return false;
}

/**
 * The MailerPress "action" the current URL is showing (confirm, manage, etc.).
 */
function pfmp_current_action() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display state from a MailerPress email link.
    return isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
}

/**
 * URL of the MailerPress "manage subscription" page (custom or default).
 */
function pfmp_manage_url() {
    return pfmp_url_for_action('manage');
}

/**
 * The four page states the Builder can simulate, matching MailerPress's own
 * ?action= values for the [mailerpress_pages] shortcode.
 */
function pfmp_builder_states() {
    return array(
        'manage'             => __('Manage', 'pages-for-mailerpress'),
        'confirm'            => __('Confirmed', 'pages-for-mailerpress'),
        'confirm_unsubscribe'=> __('Confirm unsubscribe', 'pages-for-mailerpress'),
        'unsubscribe'        => __('Unsubscribed', 'pages-for-mailerpress'),
    );
}

/**
 * Translation dictionary keyed on MailerPress's default English output.
 *
 * Each language row lists translations in the same order as $keys. If MailerPress
 * runs in a non-English base language these won't match and text is left as-is.
 */
function pfmp_dictionary() {
    $keys = array(
        'Uncheck lists to unsubscribe from them, then save your preferences.',
        'Email*',
        'Subscription status',
        'Subscribed',
        'Unsubscribed',
        'First Name',
        'Last Name',
        'Manage your subscriptions',
        'No mailing lists available.',
        'Save',
        'Saving...',
        'You have successfully unsubscribed from our emails.',
        'We have added you to our list. You will receive our next newsletter.',
        'Just click this link to unsubscribe from our emails.',
        'Yes, unsubscribe me',
        'Prefer fewer emails instead?',
        'Choose which threads you get',
    );

    $rows = array(
        'es' => array("Desmarca las listas para darte de baja de ellas y guarda tus preferencias.","Correo*","Estado de la suscripción","Suscrito","Cancelado","Nombre","Apellido","Gestiona tus suscripciones","No hay listas disponibles.","Guardar","Guardando...","Te has dado de baja de nuestros correos correctamente.","Te hemos añadido a nuestra lista. Recibirás nuestro próximo boletín.","Haz clic en este enlace para darte de baja de nuestros correos.","Sí, darme de baja","¿Prefieres recibir menos correos?","Elige qué series recibes"),
        'ar' => array("ألغِ تحديد القوائم لإلغاء الاشتراك بها، ثم احفظ تفضيلاتك.","البريد الإلكتروني*","حالة الاشتراك","مشترك","غير مشترك","الاسم الأول","اسم العائلة","أدر اشتراكاتك","لا توجد قوائم بريدية متاحة.","حفظ","جارٍ الحفظ...","تم إلغاء اشتراكك في رسائلنا بنجاح.","لقد أضفناك إلى قائمتنا. ستصلك نشرتنا القادمة.","انقر هذا الرابط لإلغاء الاشتراك في رسائلنا.","نعم، ألغِ اشتراكي","تفضّل عدداً أقل من الرسائل؟","اختر ما يصلك"),
        'fr' => array("Décochez les listes pour vous en désabonner, puis enregistrez vos préférences.","E-mail*","Statut de l'abonnement","Abonné","Désabonné","Prénom","Nom","Gérez vos abonnements","Aucune liste de diffusion disponible.","Enregistrer","Enregistrement...","Vous vous êtes désabonné de nos e-mails avec succès.","Nous vous avons ajouté à notre liste. Vous recevrez notre prochaine newsletter.","Cliquez simplement sur ce lien pour vous désabonner de nos e-mails.","Oui, désabonnez-moi","Préférez-vous recevoir moins d'e-mails ?","Choisissez les contenus que vous recevez"),
        'de' => array("Entfernen Sie die Häkchen bei den Listen, um sich abzumelden, und speichern Sie dann Ihre Einstellungen.","E-Mail*","Abonnementstatus","Abonniert","Abgemeldet","Vorname","Nachname","Verwalten Sie Ihre Abonnements","Keine Mailinglisten verfügbar.","Speichern","Wird gespeichert...","Sie haben sich erfolgreich von unseren E-Mails abgemeldet.","Wir haben Sie zu unserer Liste hinzugefügt. Sie erhalten unseren nächsten Newsletter.","Klicken Sie einfach auf diesen Link, um sich von unseren E-Mails abzumelden.","Ja, abmelden","Möchten Sie lieber weniger E-Mails erhalten?","Wählen Sie, was Sie erhalten möchten"),
        'pt' => array("Desmarque as listas para cancelar a inscrição nelas e salve suas preferências.","E-mail*","Status da assinatura","Inscrito","Cancelado","Nome","Sobrenome","Gerencie suas assinaturas","Nenhuma lista de e-mails disponível.","Salvar","Salvando...","Você cancelou a inscrição em nossos e-mails com sucesso.","Adicionamos você à nossa lista. Você receberá nossa próxima newsletter.","Basta clicar neste link para cancelar a inscrição em nossos e-mails.","Sim, cancelar minha inscrição","Prefere receber menos e-mails?","Escolha o que você recebe"),
        'it' => array("Deseleziona le liste per annullare l'iscrizione, quindi salva le tue preferenze.","E-mail*","Stato dell'iscrizione","Iscritto","Disiscritto","Nome","Cognome","Gestisci le tue iscrizioni","Nessuna lista disponibile.","Salva","Salvataggio...","Ti sei disiscritto dalle nostre email con successo.","Ti abbiamo aggiunto alla nostra lista. Riceverai la nostra prossima newsletter.","Clicca su questo link per annullare l'iscrizione alle nostre email.","Sì, disiscrivimi","Preferisci ricevere meno email?","Scegli cosa ricevere"),
        'nl' => array("Vink lijsten uit om je ervoor af te melden en sla vervolgens je voorkeuren op.","E-mail*","Abonnementsstatus","Geabonneerd","Afgemeld","Voornaam","Achternaam","Beheer je abonnementen","Geen mailinglijsten beschikbaar.","Opslaan","Opslaan...","Je bent je met succes afgemeld voor onze e-mails.","We hebben je toegevoegd aan onze lijst. Je ontvangt onze volgende nieuwsbrief.","Klik op deze link om je af te melden voor onze e-mails.","Ja, meld me af","Liever minder e-mails ontvangen?","Kies wat je ontvangt"),
        'ru' => array("Снимите отметки со списков, чтобы отписаться от них, затем сохраните настройки.","Эл. почта*","Статус подписки","Подписан","Отписан","Имя","Фамилия","Управляйте своими подписками","Нет доступных списков рассылки.","Сохранить","Сохранение...","Вы успешно отписались от наших писем.","Мы добавили вас в наш список. Вы получите нашу следующую рассылку.","Просто нажмите на эту ссылку, чтобы отписаться от наших писем.","Да, отписать меня","Предпочитаете получать меньше писем?","Выберите, что получать"),
        'pl' => array("Odznacz listy, aby się z nich wypisać, a następnie zapisz swoje preferencje.","E-mail*","Status subskrypcji","Zapisany","Wypisany","Imię","Nazwisko","Zarządzaj swoimi subskrypcjami","Brak dostępnych list mailingowych.","Zapisz","Zapisywanie...","Pomyślnie wypisałeś się z naszych e-maili.","Dodaliśmy Cię do naszej listy. Otrzymasz nasz kolejny newsletter.","Kliknij ten link, aby wypisać się z naszych e-maili.","Tak, wypisz mnie","Wolisz otrzymywać mniej e-maili?","Wybierz, co otrzymujesz"),
        'tr' => array("Aboneliğinizi iptal etmek için listelerin işaretini kaldırın, ardından tercihlerinizi kaydedin.","E-posta*","Abonelik durumu","Abone","Aboneliği iptal edildi","Ad","Soyad","Aboneliklerinizi yönetin","Kullanılabilir posta listesi yok.","Kaydet","Kaydediliyor...","E-postalarımızdan başarıyla çıkış yaptınız.","Sizi listemize ekledik. Bir sonraki bültenimizi alacaksınız.","E-postalarımızdan çıkmak için bu bağlantıya tıklamanız yeterli.","Evet, aboneliğimi iptal et","Daha az e-posta almayı mı tercih edersiniz?","Neleri alacağınızı seçin"),
        'sv' => array("Avmarkera listor för att avsluta prenumerationen på dem och spara sedan dina inställningar.","E-post*","Prenumerationsstatus","Prenumererar","Avslutad","Förnamn","Efternamn","Hantera dina prenumerationer","Inga sändlistor tillgängliga.","Spara","Sparar...","Du har avslutat prenumerationen på våra e-postmeddelanden.","Vi har lagt till dig i vår lista. Du kommer att få vårt nästa nyhetsbrev.","Klicka bara på den här länken för att avsluta prenumerationen på våra e-postmeddelanden.","Ja, avsluta min prenumeration","Vill du hellre få färre e-postmeddelanden?","Välj vad du vill få"),
        'da' => array("Fjern markeringen af lister for at afmelde dig dem, og gem derefter dine præferencer.","E-mail*","Abonnementsstatus","Tilmeldt","Afmeldt","Fornavn","Efternavn","Administrer dine abonnementer","Ingen mailinglister tilgængelige.","Gem","Gemmer...","Du er blevet afmeldt vores e-mails.","Vi har tilføjet dig til vores liste. Du modtager vores næste nyhedsbrev.","Klik blot på dette link for at afmelde vores e-mails.","Ja, afmeld mig","Foretrækker du at modtage færre e-mails?","Vælg, hvad du modtager"),
        'nb' => array("Fjern haken fra listene for å melde deg av dem, og lagre deretter innstillingene dine.","E-post*","Abonnementsstatus","Abonnert","Avmeldt","Fornavn","Etternavn","Administrer abonnementene dine","Ingen e-postlister tilgjengelig.","Lagre","Lagrer...","Du har meldt deg av e-postene våre.","Vi har lagt deg til i listen vår. Du vil motta vårt neste nyhetsbrev.","Klikk på denne lenken for å melde deg av e-postene våre.","Ja, meld meg av","Foretrekker du å motta færre e-poster?","Velg hva du vil motta"),
        'fi' => array("Poista listojen valinta peruuttaaksesi niiden tilauksen ja tallenna sitten asetuksesi.","Sähköposti*","Tilauksen tila","Tilattu","Tilaus peruttu","Etunimi","Sukunimi","Hallitse tilauksiasi","Ei postituslistoja saatavilla.","Tallenna","Tallennetaan...","Olet peruuttanut sähköpostiemme tilauksen onnistuneesti.","Olemme lisänneet sinut listallemme. Saat seuraavan uutiskirjeemme.","Napsauta tätä linkkiä peruuttaaksesi sähköpostiemme tilauksen.","Kyllä, peruuta tilaukseni","Haluatko mieluummin vähemmän sähköposteja?","Valitse, mitä haluat vastaanottaa"),
        'cs' => array("Zrušte zaškrtnutí seznamů, ze kterých se chcete odhlásit, a poté uložte své předvolby.","E-mail*","Stav odběru","Odebíráno","Odhlášeno","Jméno","Příjmení","Spravujte své odběry","Nejsou k dispozici žádné mailing listy.","Uložit","Ukládání...","Úspěšně jste se odhlásili z odběru našich e-mailů.","Přidali jsme vás do našeho seznamu. Obdržíte náš další newsletter.","Stačí kliknout na tento odkaz a odhlásit se z odběru našich e-mailů.","Ano, odhlásit mě","Chcete raději dostávat méně e-mailů?","Vyberte si, co chcete dostávat"),
        'el' => array("Καταργήστε την επιλογή των λιστών για να διαγραφείτε από αυτές και, στη συνέχεια, αποθηκεύστε τις προτιμήσεις σας.","Email*","Κατάσταση συνδρομής","Εγγεγραμμένος","Διαγραμμένος","Όνομα","Επώνυμο","Διαχειριστείτε τις συνδρομές σας","Δεν υπάρχουν διαθέσιμες λίστες.","Αποθήκευση","Αποθήκευση...","Διαγραφήκατε με επιτυχία από τα email μας.","Σας προσθέσαμε στη λίστα μας. Θα λάβετε το επόμενο ενημερωτικό μας δελτίο.","Απλώς κάντε κλικ σε αυτόν τον σύνδεσμο για να διαγραφείτε από τα email μας.","Ναι, διαγράψτε με","Προτιμάτε λιγότερα email;","Επιλέξτε τι θα λαμβάνετε"),
        'he' => array("בטלו את הסימון של הרשימות כדי לבטל מהן את המנוי, ולאחר מכן שמרו את ההעדפות שלכם.","אימייל*","סטטוס מנוי","מנוי","המנוי בוטל","שם פרטי","שם משפחה","נהלו את המנויים שלכם","אין רשימות תפוצה זמינות.","שמירה","שומר...","ביטלת בהצלחה את המנוי לאימיילים שלנו.","הוספנו אותך לרשימה שלנו. תקבל את הניוזלטר הבא שלנו.","פשוט לחצו על קישור זה כדי לבטל את המנוי לאימיילים שלנו.","כן, בטלו את המנוי שלי","מעדיפים לקבל פחות אימיילים?","בחרו מה לקבל"),
        'hi' => array("सूचियों से सदस्यता समाप्त करने के लिए उन्हें अनचेक करें, फिर अपनी प्राथमिकताएँ सहेजें।","ईमेल*","सदस्यता स्थिति","सदस्यता ली गई","सदस्यता समाप्त","पहला नाम","अंतिम नाम","अपनी सदस्यताएँ प्रबंधित करें","कोई मेलिंग सूची उपलब्ध नहीं है।","सहेजें","सहेजा जा रहा है...","आपने हमारे ईमेल की सदस्यता सफलतापूर्वक समाप्त कर दी है।","हमने आपको अपनी सूची में जोड़ लिया है। आपको हमारा अगला न्यूज़लेटर मिलेगा।","हमारे ईमेल की सदस्यता समाप्त करने के लिए बस इस लिंक पर क्लिक करें।","हाँ, मेरी सदस्यता समाप्त करें","इसके बजाय कम ईमेल पसंद करते हैं?","चुनें कि आप क्या प्राप्त करते हैं"),
        'ja' => array("配信を停止するリストのチェックを外し、設定を保存してください。","メールアドレス*","購読状況","購読中","購読解除","名","姓","購読を管理する","利用可能なメーリングリストはありません。","保存","保存中...","メールの配信を正常に停止しました。","リストに追加しました。次のニュースレターをお届けします。","このリンクをクリックして、メールの配信を停止してください。","はい、配信を停止します","メールの数を減らしますか？","受け取る内容を選択してください"),
        'ko' => array("구독을 취소하려면 목록의 선택을 해제한 후 기본 설정을 저장하세요.","이메일*","구독 상태","구독 중","구독 취소됨","이름","성","구독 관리","사용 가능한 메일링 리스트가 없습니다.","저장","저장 중...","이메일 구독이 성공적으로 취소되었습니다.","목록에 추가했습니다. 다음 뉴스레터를 받아보시게 됩니다.","이 링크를 클릭하면 이메일 구독이 취소됩니다.","예, 구독을 취소합니다","이메일을 더 적게 받으시겠어요?","받을 내용을 선택하세요"),
        'zh' => array("取消勾选要退订的列表，然后保存您的偏好设置。","电子邮件*","订阅状态","已订阅","已退订","名字","姓氏","管理您的订阅","没有可用的邮件列表。","保存","正在保存...","您已成功退订我们的邮件。","我们已将您加入列表。您将收到我们的下一期通讯。","只需点击此链接即可退订我们的邮件。","是的，退订我","希望减少邮件数量？","选择您想接收的内容"),
        'th' => array("ยกเลิกการเลือกรายการเพื่อยกเลิกการสมัครรับข้อมูล จากนั้นบันทึกการตั้งค่าของคุณ","อีเมล*","สถานะการสมัครรับข้อมูล","สมัครรับข้อมูลแล้ว","ยกเลิกการสมัครแล้ว","ชื่อ","นามสกุล","จัดการการสมัครรับข้อมูลของคุณ","ไม่มีรายชื่อผู้รับจดหมาย","บันทึก","กำลังบันทึก...","คุณยกเลิกการสมัครรับอีเมลของเราเรียบร้อยแล้ว","เราได้เพิ่มคุณลงในรายชื่อของเราแล้ว คุณจะได้รับจดหมายข่าวฉบับถัดไปของเรา","เพียงคลิกลิงก์นี้เพื่อยกเลิกการสมัครรับอีเมลของเรา","ใช่ ยกเลิกการสมัครของฉัน","ต้องการรับอีเมลน้อยลงหรือไม่?","เลือกสิ่งที่คุณต้องการรับ"),
        'vi' => array("Bỏ chọn các danh sách để hủy đăng ký, sau đó lưu tùy chọn của bạn.","Email*","Trạng thái đăng ký","Đã đăng ký","Đã hủy đăng ký","Tên","Họ","Quản lý đăng ký của bạn","Không có danh sách gửi thư nào.","Lưu","Đang lưu...","Bạn đã hủy đăng ký email của chúng tôi thành công.","Chúng tôi đã thêm bạn vào danh sách. Bạn sẽ nhận được bản tin tiếp theo của chúng tôi.","Chỉ cần nhấp vào liên kết này để hủy đăng ký email của chúng tôi.","Có, hủy đăng ký của tôi","Bạn muốn nhận ít email hơn?","Chọn nội dung bạn nhận"),
        'id' => array("Hapus centang daftar untuk berhenti berlangganan, lalu simpan preferensi Anda.","Email*","Status langganan","Berlangganan","Berhenti berlangganan","Nama depan","Nama belakang","Kelola langganan Anda","Tidak ada milis yang tersedia.","Simpan","Menyimpan...","Anda berhasil berhenti berlangganan email kami.","Kami telah menambahkan Anda ke daftar kami. Anda akan menerima buletin kami berikutnya.","Cukup klik tautan ini untuk berhenti berlangganan email kami.","Ya, berhenti langganan","Lebih suka menerima lebih sedikit email?","Pilih apa yang Anda terima"),
        'uk' => array("Зніміть позначки зі списків, щоб відписатися від них, а потім збережіть налаштування.","Ел. пошта*","Статус підписки","Підписано","Відписано","Ім'я","Прізвище","Керуйте своїми підписками","Немає доступних списків розсилки.","Зберегти","Збереження...","Ви успішно відписалися від наших листів.","Ми додали вас до нашого списку. Ви отримаєте нашу наступну розсилку.","Просто натисніть це посилання, щоб відписатися від наших листів.","Так, відписати мене","Бажаєте отримувати менше листів?","Виберіть, що отримувати"),
        'ro' => array("Debifați listele pentru a vă dezabona de la ele, apoi salvați preferințele.","E-mail*","Starea abonamentului","Abonat","Dezabonat","Prenume","Nume","Gestionați-vă abonamentele","Nu există liste de corespondență disponibile.","Salvați","Se salvează...","V-ați dezabonat cu succes de la e-mailurile noastre.","V-am adăugat pe lista noastră. Veți primi următorul nostru buletin informativ.","Faceți clic pe acest link pentru a vă dezabona de la e-mailurile noastre.","Da, dezabonează-mă","Preferați să primiți mai puține e-mailuri?","Alegeți ce primiți"),
        'hu' => array("A leiratkozáshoz törölje a listák jelölését, majd mentse a beállításait.","E-mail*","Feliratkozás állapota","Feliratkozva","Leiratkozva","Keresztnév","Vezetéknév","Feliratkozásaid kezelése","Nincs elérhető levelezőlista.","Mentés","Mentés...","Sikeresen leiratkozott az e-mailjeinkről.","Hozzáadtuk a listánkhoz. Meg fogja kapni a következő hírlevelünket.","Csak kattintson erre a linkre az e-mailjeinkről való leiratkozáshoz.","Igen, iratkoztass le","Inkább kevesebb e-mailt szeretne kapni?","Válassza ki, mit szeretne kapni"),
        'fa' => array("برای لغو اشتراک، تیک فهرست‌ها را بردارید و سپس تنظیمات خود را ذخیره کنید.","ایمیل*","وضعیت اشتراک","مشترک","لغو اشتراک شده","نام","نام خانوادگی","اشتراک‌های خود را مدیریت کنید","هیچ فهرست پستی موجود نیست.","ذخیره","در حال ذخیره...","شما با موفقیت اشتراک ایمیل‌های ما را لغو کردید.","شما را به فهرست خود اضافه کردیم. خبرنامه بعدی ما را دریافت خواهید کرد.","برای لغو اشتراک ایمیل‌های ما کافی است روی این پیوند کلیک کنید.","بله، اشتراک من را لغو کن","ترجیح می‌دهید ایمیل‌های کمتری دریافت کنید؟","انتخاب کنید چه چیزی دریافت کنید"),
        'bg' => array("Премахнете отметките от списъците, за да се отпишете от тях, след което запазете предпочитанията си.","Имейл*","Състояние на абонамента","Абониран","Отписан","Име","Фамилия","Управлявайте абонаментите си","Няма налични пощенски списъци.","Запазване","Запазване...","Успешно се отписахте от нашите имейли.","Добавихме ви към нашия списък. Ще получите следващия ни бюлетин.","Просто щракнете върху този линк, за да се отпишете от нашите имейли.","Да, отпишете ме","Предпочитате ли да получавате по-малко имейли?","Изберете какво да получавате"),
    );

    $dict = array();
    foreach ($rows as $lang => $vals) {
        if (count($vals) === count($keys)) {
            $dict[$lang] = array_combine($keys, $vals);
        }
    }
    return $dict;
}
