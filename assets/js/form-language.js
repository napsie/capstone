(function () {
    'use strict';

    const translations = {
        'apply for senior id': 'Mag-apply para sa Senior ID',
        'apply for senior benefits': 'Mag-apply para sa Benepisyo ng Senior',
        'select transaction portal': 'Pumili ng Transaksyon', 'choose a senior benefit': 'Pumili ng Benepisyo para sa Senior',
        'verify senior identity': 'Beripikahin ang Pagkakakilanlan ng Senior', 'verify identity': 'Beripikahin ang Pagkakakilanlan',
        'application and senior citizen information': 'Impormasyon ng Aplikasyon at Senior Citizen',
        'personal identity': 'Personal na Pagkakakilanlan', 'current home address': 'Kasalukuyang Tirahan',
        "select the requested benefit, then enter the senior's details exactly as they appear in official records.": 'Piliin ang hinihiling na benepisyo, pagkatapos ay ilagay ang detalye ng senior ayon sa opisyal na rekord.',
        'use an active 11-digit philippine mobile number.': 'Gumamit ng aktibong 11-digit na Philippine mobile number.',
        'senior citizen id number': 'Numero ng Senior Citizen ID',
        'permanent id token': 'Permanenteng ID Token',
        'last name': 'Apelyido', 'first name': 'Pangalan', 'middle name': 'Gitnang Pangalan', 'suffix': 'Hulapi',
        'full name': 'Buong Pangalan', 'birth date': 'Petsa ng Kapanganakan', 'date of birth': 'Petsa ng Kapanganakan',
        'contact number': 'Numero ng Telepono', 'mobile number': 'Numero ng Cellphone',
        'place of birth': 'Lugar ng Kapanganakan', "mother's maiden name": 'Apelyido ng Ina Bago Ikasal',
        'sex': 'Kasarian', 'gender': 'Kasarian', 'male': 'Lalaki', 'female': 'Babae',
        'civil status': 'Katayuang Sibil', 'single': 'Walang Asawa', 'married': 'May Asawa', 'widowed': 'Balo', 'separated': 'Hiwalay',
        'house / unit no.': 'Numero ng Bahay / Unit', 'street / subdivision': 'Kalye / Subdivision',
        'complete address': 'Kumpletong Tirahan', 'barangay': 'Barangay', 'city': 'Lungsod', 'province': 'Lalawigan',
        'zip code': 'ZIP Code', 'nearest landmark': 'Pinakamalapit na Palatandaan', "senior's email address": 'Email Address ng Senior',
        'city / municipality, province': 'Lungsod / Munisipalidad, Lalawigan', 'full maiden name': 'Buong Apelyido Bago Ikasal',
        'mobility status': 'Kalagayan sa Pagkilos', 'living arrangement': 'Kalagayan sa Tinitirhan',
        'id application purpose': 'Layunin ng Aplikasyon sa ID', 'application purpose': 'Layunin ng Aplikasyon',
        'senior citizen id registration': 'Pagpaparehistro ng Senior Citizen ID',
        'update or replace senior id': 'I-update o Palitan ang Senior ID',
        'new / first-time': 'Bago / Unang Beses', 'lost id replacement': 'Kapalit ng Nawalang ID',
        'information change': 'Pagbabago ng Impormasyon', 'transfer': 'Paglipat',
        'health status': 'Kalagayan ng Kalusugan', 'frail/sickly or pwd details': 'Detalye ng Mahina, May Sakit, o PWD',
        'condition / illness': 'Kondisyon / Sakit', 'emergency contact name': 'Pangalan ng Emergency Contact',
        'emergency contact number': 'Numero ng Emergency Contact', 'relationship': 'Relasyon',
        'control number': 'Control Number', 'atm number or temporary cash card stub number': 'Numero ng ATM o Pansamantalang Cash Card Stub',
        'currently receiving any pension?': 'Kasalukuyang tumatanggap ng pensyon?', 'pension source': 'Pinagmumulan ng Pensyon',
        'current monthly pension amount': 'Halaga ng Buwanang Pensyon', 'monthly pension amount': 'Halaga ng Buwanang Pensyon',
        'receives regular family support?': 'Regular na sinusuportahan ng pamilya?', 'family support — cash amount': 'Suporta ng Pamilya — Halagang Pera',
        'type of family support': 'Uri ng Suporta ng Pamilya', 'permanent source of income?': 'May permanenteng pinagkakakitaan?',
        'permanent income source (if yes)': 'Permanenteng Pinagkakakitaan (kung oo)', 'owns house?': 'May sariling bahay?',
        'renter?': 'Nangungupahan?', 'name to appear on card': 'Pangalang Ilalagay sa Card', 'id presented': 'ID na Ipinakita',
        'nationality': 'Nasyonalidad', 'source of funds': 'Pinagmumulan ng Pondo', 'milestone age': 'Natanging Edad',
        'claimant name': 'Pangalan ng Claimant', 'claimant contact': 'Contact ng Claimant',
        'date of passing': 'Petsa ng Pagpanaw', 'relationship to deceased': 'Relasyon sa Yumao',
        'deceased senior id number': 'Senior ID Number ng Yumao', 'landbank cash card number': 'Numero ng Landbank Cash Card',
        'name of applicant / claimant': 'Pangalan ng Aplikante / Claimant', 'claimant contact number': 'Numero ng Claimant',
        'proof of relationship': 'Patunay ng Relasyon', 'affidavit type (if applicable)': 'Uri ng Affidavit (kung naaangkop)',
        'remarks / notes': 'Mga Puna / Tala', 'birth cert / negative of birth': 'Birth Certificate / Negative of Birth',
        'local senior pension form': 'Form ng Lokal na Pensyon para sa Senior',
        'land bank cash card enrollment': 'Pagpapatala para sa Land Bank Cash Card',
        'milestone cash gift': 'Milestone Cash Gift', 'burial assistance': 'Tulong sa Pagpapalibing',
        'required documents': 'Mga Kinakailangang Dokumento', 'review and consent': 'Pagsusuri at Pahintulot',
        'barangay residency certificate': 'Sertipiko ng Paninirahan sa Barangay', '2-year comelec certification': '2-Taong COMELEC Certification',
        'recent 1×1 id photo': 'Kamakailang 1×1 ID Photo', 'valid government id': 'Balidong Government ID',
        'confirm and submit': 'Kumpirmahin at Isumite', 'submit application': 'Isumite ang Aplikasyon',
        'change benefit': 'Palitan ang Benepisyo', 'go to editable information': 'Pumunta sa Impormasyong Maaaring Baguhin',
        'proceed with application': 'Magpatuloy sa Aplikasyon', 'what you get': 'Mga Matatanggap',
        'requirements to apply': 'Mga Kinakailangan sa Pag-apply', 'documents needed': 'Mga Dokumentong Kailangan',
        'continue': 'Magpatuloy', 'back': 'Bumalik', 'next': 'Susunod', 'previous': 'Nakaraan', 'cancel': 'Kanselahin',
        'save': 'I-save', 'update': 'I-update', 'search': 'Maghanap', 'filter': 'Salain', 'clear': 'Linisin',
        'yes': 'Oo', 'no': 'Hindi', 'select': 'Pumili', 'select purpose': 'Pumili ng layunin', 'select condition': 'Pumili ng kondisyon',
        '— select sex —': '— Pumili ng Kasarian —', '— select civil status —': '— Pumili ng Katayuang Sibil —',
        'username': 'Pangalan ng Gumagamit', 'password': 'Password', 'confirm password': 'Kumpirmahin ang Password',
        'email': 'Email', 'role': 'Tungkulin', 'profile photo': 'Larawan sa Profile', 'master password': 'Master Password',
        'search activity': 'Maghanap ng Aktibidad', 'location': 'Lokasyon', 'event type': 'Uri ng Kaganapan',
        'status': 'Katayuan', 'home visit date': 'Petsa ng Pagbisita sa Bahay', 'assigned personnel': 'Nakatalagang Tauhan',
        'visit status': 'Katayuan ng Pagbisita', 'final evaluation': 'Pinal na Pagsusuri',
        'reason for decision': 'Dahilan ng Desisyon', 'observations / visit summary': 'Mga Obserbasyon / Buod ng Pagbisita',
        'file format': 'Format ng File', 'application type': 'Uri ng Aplikasyon', 'year (if no date range)': 'Taon (kung walang saklaw ng petsa)',
        'date from': 'Petsa Mula', 'date to': 'Petsa Hanggang', 'choose document': 'Pumili ng Dokumento',
        'new password': 'Bagong Password', 'confirm new password': 'Kumpirmahin ang Bagong Password'
    };

    const translatableSelector = 'form label, form legend, form h2, form h3, form h4, form button, form option, form small, form p, form .form-hint, form .section-title, form [data-document-label]';
    const originalText = new WeakMap();

    function translateElement(element) {
        Array.from(element.childNodes).forEach(node => {
            if (node.nodeType !== Node.TEXT_NODE) return;
            if (!originalText.has(node)) originalText.set(node, node.nodeValue || '');
            const raw = originalText.get(node);
            const key = raw.trim().replace(/\s+/g, ' ').toLowerCase();
            const translated = translations[key];
            node.nodeValue = translated && translated.toLowerCase() !== key
                ? raw.replace(raw.trim(), `${raw.trim()} / ${translated}`)
                : raw;
        });
    }

    function translateInputs(root) {
        root.querySelectorAll('form input[placeholder], form textarea[placeholder]').forEach(input => {
            if (!input.dataset.slEnglishPlaceholder) input.dataset.slEnglishPlaceholder = input.placeholder;
            const key = input.dataset.slEnglishPlaceholder.trim().toLowerCase();
            const translated = translations[key];
            input.placeholder = translated && translated.toLowerCase() !== key
                ? `${input.dataset.slEnglishPlaceholder} / ${translated}`
                : input.dataset.slEnglishPlaceholder;
        });
    }

    function applyLanguage(root = document) {
        root.querySelectorAll(translatableSelector).forEach(translateElement);
        translateInputs(root);
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyLanguage();
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) applyLanguage(node);
            }));
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
