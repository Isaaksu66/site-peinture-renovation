document.addEventListener('DOMContentLoaded', () => {
    // ==========================================================================
    // MENU MOBILE HAMBURGER
    // ==========================================================================
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const navMenu = document.querySelector('#main-nav ul');

    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Fermer le menu lors du clic sur un lien
        const navLinks = navMenu.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                menuToggle.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });
    }

    // ==========================================================================
    // GALERIE FILTRABLE (PAGES RÉALISATIONS)
    // ==========================================================================
    const filterButtons = document.querySelectorAll('.filter-btn');
    const projectCards = document.querySelectorAll('.project-card');

    if (filterButtons.length > 0 && projectCards.length > 0) {
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Retirer la classe active de tous les boutons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                // Ajouter la classe active au bouton cliqué
                button.classList.add('active');

                const filterValue = button.getAttribute('data-filter');

                projectCards.forEach(card => {
                    if (filterValue === 'all') {
                        card.style.display = 'block';
                    } else {
                        const cardCategory = card.getAttribute('data-category');
                        if (cardCategory === filterValue) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });
            });
        });
    }

    // ==========================================================================
    // CARTE INTERACTIVE LEAFLET JS (PAGE CONTACT)
    // ==========================================================================
    const mapElement = document.getElementById('map');
    if (mapElement && typeof L !== 'undefined') {
        const mapConfig = window.mapConfig || {
            company_name: "Cetin Family",
            address: "646 Rue Einstein, 71000 Mâcon",
            latitude: 46.291726,
            longitude: 4.817687,
            radius: 50000,
            city: "Mâcon"
        };
        
        // Initialisation de la carte avec zoom satellite rapproché et animations fluides
        const map = L.map('map', {
            center: [mapConfig.latitude, mapConfig.longitude],
            zoom: 15, // Zoom professionnel pour afficher le quartier et les repères
            scrollWheelZoom: false,
            zoomControl: true,
            fadeAnimation: true,
            zoomAnimation: true
        });

        // Calque de tuiles Google Roadmap (Plan de la ville)
        const roadmapLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Maps'
        });

        // Calque de tuiles Google Hybrid (Satellite + Routes + Noms) pour voir les vrais bâtiments
        const satelliteLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Maps'
        });

        // Par défaut, afficher le Plan de la ville
        roadmapLayer.addTo(map);

        // Sélecteur de calques (Plan de la ville / Vue Satellite)
        const baseMaps = {
            "Plan de la ville": roadmapLayer,
            "Vue Satellite": satelliteLayer
        };
        L.control.layers(baseMaps, null, { position: 'topright' }).addTo(map);

        // Icône personnalisée SVG vectorielle dorée & bronze avec effet de pulsation moderne
        const customPinIcon = L.divIcon({
            html: `
                <div class="custom-pin-wrapper">
                    <div class="custom-pin-pulse"></div>
                    <div class="custom-pin">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2Z" fill="#c5a880" stroke="#161a19" stroke-width="1.8" stroke-linejoin="round"/>
                            <circle cx="12" cy="9" r="3.5" fill="#161a19" />
                        </svg>
                    </div>
                </div>
            `,
            className: 'custom-map-marker-icon',
            iconSize: [40, 40],
            iconAnchor: [20, 34],
            popupAnchor: [0, -32]
        });

        // Contenu du Popup Premium avec détails de l'adresse et bouton Itinéraire vers Google Maps
        const popupContent = `
            <div class="map-popup-card">
                <h4 class="map-popup-title">${mapConfig.company_name}</h4>
                <p class="map-popup-text">Notre atelier & bureau principal</p>
                <p class="map-popup-address">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="popup-addr-icon"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    ${mapConfig.address}
                </p>
                <div class="map-popup-actions">
                    <a href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(mapConfig.address)}" target="_blank" class="map-popup-btn btn-route">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="route-icon"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
                        Calculer l'itinéraire
                    </a>
                </div>
            </div>
        `;

        // Ajout du marqueur d'atelier
        const companyMarker = L.marker([mapConfig.latitude, mapConfig.longitude], { icon: customPinIcon }).addTo(map);
        companyMarker.bindPopup(popupContent, {
            closeButton: true,
            className: 'custom-leaflet-popup',
            autoPanPadding: [50, 50]
        }).openPopup();

        // Zone d'intervention dynamique stylisée (cercle élégant pointillé bronze/doré)
        L.circle([mapConfig.latitude, mapConfig.longitude], {
            color: '#c5a880',
            fillColor: '#c5a880',
            fillOpacity: 0.07,
            weight: 2,
            dashArray: '6, 8',
            radius: mapConfig.radius
        }).addTo(map);

        // Bouton de recentrage personnalisé (style Google Maps / iOS Maps)
        const RecenterControl = L.Control.extend({
            options: { position: 'topleft' },
            onAdd: function() {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-recenter-control');
                container.innerHTML = `
                    <a href="#" title="Recentrer sur l'atelier Cetin Family" role="button" aria-label="Recentrer sur l'atelier" class="recenter-map-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#161a19" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="3"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line></svg>
                    </a>
                `;
                container.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    map.setView([mapConfig.latitude, mapConfig.longitude], 15);
                    companyMarker.openPopup();
                };
                return container;
            }
        });
        map.addControl(new RecenterControl());
    }

    // ==========================================================================
    // MODAL DE DETAILS DU DEVIS (ESPACE ADMIN)
    // ==========================================================================
    const detailButtons = document.querySelectorAll('.btn-detail');
    const modal = document.getElementById('devis-modal');
    
    if (detailButtons.length > 0 && modal) {
        const closeSpan = modal.querySelector('.modal-close');
        const closeBtn = modal.querySelector('.modal-close-btn');
        
        const modalTitle = document.getElementById('modal-title');
        const modalClientName = document.getElementById('modal-client-name');
        const modalEmailLink = document.getElementById('modal-email-link');
        const modalPhoneLink = document.getElementById('modal-phone-link');
        const modalLocation = document.getElementById('modal-location');
        const modalProjectType = document.getElementById('modal-project-type');
        const modalSurface = document.getElementById('modal-surface');
        const modalDate = document.getElementById('modal-date');
        const modalStatus = document.getElementById('modal-status');
        const modalMessage = document.getElementById('modal-message');

        const modalPdfBtn = document.getElementById('modal-pdf-btn');

        detailButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const client = btn.getAttribute('data-client');
                const email = btn.getAttribute('data-email');
                const phone = btn.getAttribute('data-phone');
                const location = btn.getAttribute('data-location');
                const project = btn.getAttribute('data-project');
                const surface = btn.getAttribute('data-surface');
                const date = btn.getAttribute('data-date');
                const message = btn.getAttribute('data-message');
                const status = btn.getAttribute('data-status');

                // Remplissage dynamique des champs
                modalTitle.textContent = `Demande de devis #${id}`;
                modalClientName.textContent = client;
                
                modalEmailLink.textContent = email;
                modalEmailLink.href = `mailto:${email}`;
                
                modalPhoneLink.textContent = phone;
                modalPhoneLink.href = `tel:${phone}`;
                
                modalLocation.textContent = location;
                modalProjectType.textContent = project;
                modalSurface.textContent = surface;
                modalDate.textContent = date;
                
                // Mettre à jour l'URL du bouton d'export PDF
                if (modalPdfBtn) {
                    modalPdfBtn.href = `export_devis_pdf.php?id=${id}`;
                }

                // Badge de statut stylisé
                modalStatus.textContent = status.replace('_', ' ').toUpperCase();
                modalStatus.className = `status-tag status-tag-${status}`;
                
                modalMessage.textContent = message;

                // Affichage avec animation et blocage du scroll en arrière-plan
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });

        const closeModal = () => {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        };

        if (closeSpan) closeSpan.addEventListener('click', closeModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        // Fermeture du modal lors d'un clic à l'extérieur de la boîte de contenu
        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    // ==========================================================================
    // BANDEAU DE CONSENTEMENT AUX COOKIES (RGPD)
    // ==========================================================================
    const cookieBanner = document.getElementById('cookie-banner');
    const btnAccept = document.getElementById('btn-cookie-accept');
    const btnRefuse = document.getElementById('btn-cookie-refuse');

    if (cookieBanner && btnAccept && btnRefuse) {
        // Vérifier si le choix a déjà été enregistré
        const cookieChoice = localStorage.getItem('cookieConsent');

        if (!cookieChoice) {
            // Afficher le bandeau avec une transition fluide après 1 seconde
            setTimeout(() => {
                cookieBanner.classList.add('active');
                document.body.classList.add('cookie-banner-open');
            }, 1000);
        }

        btnAccept.addEventListener('click', () => {
            localStorage.setItem('cookieConsent', 'accepted');
            cookieBanner.classList.remove('active');
            document.body.classList.remove('cookie-banner-open');
        });

        btnRefuse.addEventListener('click', () => {
            localStorage.setItem('cookieConsent', 'refused');
            cookieBanner.classList.remove('active');
            document.body.classList.remove('cookie-banner-open');
        });
    }

    // ==========================================================================
    // NAVIGATION PAR ONGLETS (ESPACE ADMIN CMS)
    // ==========================================================================
    const tabButtons = document.querySelectorAll('.admin-tab-btn');
    const tabContents = document.querySelectorAll('.admin-tab-content');
    const breadcrumbTab = document.getElementById('active-breadcrumb-tab');

    function updateBreadcrumb(btn) {
        if (!breadcrumbTab) return;
        // Supprimer les emojis du texte du bouton pour le fil d'Ariane
        const text = btn.textContent.replace(/[\u2700-\u27BF]|[\uE000-\uF8FF]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|[\u2011-\u26FF]|\uD83E[\uDD10-\uDDFF]/g, '').trim();
        breadcrumbTab.textContent = text;
    }

    if (tabButtons.length > 0 && tabContents.length > 0) {
        // Au chargement, activer le premier onglet ou celui spécifié dans l'URL hash
        const activeTabHash = window.location.hash;
        let initialTabActive = false;

        if (activeTabHash) {
            const targetBtn = document.querySelector(`.admin-tab-btn[data-tab="${activeTabHash.replace('#', '')}"]`);
            const targetContent = document.querySelector(activeTabHash);
            if (targetBtn && targetContent) {
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                targetBtn.classList.add('active');
                targetContent.classList.add('active');
                updateBreadcrumb(targetBtn);
                initialTabActive = true;
            }
        }

        if (!initialTabActive) {
            tabButtons[0].classList.add('active');
            tabContents[0].classList.add('active');
            updateBreadcrumb(tabButtons[0]);
        }

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTabId = btn.getAttribute('data-tab');
                
                // Désactiver tous les onglets
                tabButtons.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Activer l'onglet cliqué
                btn.classList.add('active');
                const targetContent = document.getElementById(targetTabId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
                
                // Mettre à jour le fil d'Ariane
                updateBreadcrumb(btn);
                
                // Mettre à jour l'ancre dans l'URL sans scroller
                history.replaceState(null, null, `#${targetTabId}`);
            });
        });
    }

    // Gestion des sous-onglets CMS (Pages Modifiables)
    const pageLinks = document.querySelectorAll('.cms-page-link');
    const subtabContents = document.querySelectorAll('.cms-subtab-content');
    if (pageLinks.length > 0 && subtabContents.length > 0) {
        pageLinks.forEach(link => {
            link.addEventListener('click', () => {
                const targetSubtabId = link.getAttribute('data-subtab');
                pageLinks.forEach(l => l.classList.remove('active'));
                subtabContents.forEach(c => c.classList.remove('active'));
                
                link.classList.add('active');
                const targetContent = document.getElementById(targetSubtabId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
    }

    // ==========================================================================
    // BOUTON RETOUR EN HAUT (SCROLL TO TOP)
    // ==========================================================================
    const scrollToTopBtn = document.getElementById('scroll-to-top');
    
    if (scrollToTopBtn) {
        // Afficher/masquer le bouton en fonction du défilement
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollToTopBtn.classList.add('visible');
            } else {
                scrollToTopBtn.classList.remove('visible');
            }
        });
        
        // Action au clic (défilement fluide vers le haut)
        scrollToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});
