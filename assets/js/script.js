/* STREAMING_CHUNK: DOM Element Selections... */
document.addEventListener('DOMContentLoaded', () => {
    // Navigation Elements
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileMenu = document.getElementById('mobileMenu');

    // Modal Elements
    const authModal = document.getElementById('authModal');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalBody = document.getElementById('modalBody');

    // Form Elements
    const contactForm = document.getElementById('contactForm');
    const formAlert = document.getElementById('formAlert');

    // Footer Links
    const disclaimerLink = document.getElementById('disclaimerLink');
    const privacyLink = document.getElementById('privacyLink');

    /* STREAMING_CHUNK: Hamburger Menu Toggle... */
    if (hamburgerBtn && mobileMenu) {
        hamburgerBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
        });
    }

    /* STREAMING_CHUNK: Modal Logic Handlers... */
    const openModal = (type) => {
        if (type === 'register') {
            modalBody.innerHTML = `
                <h3 style="font-size:22px; font-weight:800; margin-bottom:8px;">Crear una cuenta</h3>
                <p style="font-size:14px; color:#64748B; margin-bottom:20px;">Comienza a organizar los turnos de tu negocio hoy.</p>
                <form id="modalAuthForm" style="display:flex; flex-direction:column; gap:14px;">
                    <div class="form-group">
                        <label>Nombre de tu negocio</label>
                        <input type="text" required placeholder="Ej. Mi Consultorio">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" required placeholder="tu@email.com">
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn btn-orange w-full" style="padding:12px; margin-top:6px;">Registrarme Gratis</button>
                </form>
                <p style="font-size:12px; text-align:center; color:#64748B; margin-top:16px;">
                    ¿Ya tienes cuenta? <a href="#" id="switchToLogin" style="color:#1B3687; font-weight:700; text-decoration:none;">Inicia sesión</a>
                </p>
            `;
        } else {
            modalBody.innerHTML = `
                <h3 style="font-size:22px; font-weight:800; margin-bottom:8px;">Iniciar Sesión</h3>
                <p style="font-size:14px; color:#64748B; margin-bottom:20px;">Ingresa a tu tablero de gestión de turnos.</p>
                <form id="modalAuthForm" style="display:flex; flex-direction:column; gap:14px;">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" required placeholder="tu@email.com">
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn btn-navy w-full" style="padding:12px; margin-top:6px;">Ingresar</button>
                </form>
                <p style="font-size:12px; text-align:center; color:#64748B; margin-top:16px;">
                    ¿No tienes cuenta? <a href="#" id="switchToRegister" style="color:#FF6B00; font-weight:700; text-decoration:none;">Regístrate</a>
                </p>
            `;
        }

        authModal.classList.remove('hidden');

        // Sub-navigation inside modal
        const switchToLogin = document.getElementById('switchToLogin');
        const switchToRegister = document.getElementById('switchToRegister');
        const modalAuthForm = document.getElementById('modalAuthForm');

        if (switchToLogin) switchToLogin.addEventListener('click', (e) => { e.preventDefault(); openModal('login'); });
        if (switchToRegister) switchToRegister.addEventListener('click', (e) => { e.preventDefault(); openModal('register'); });

        if (modalAuthForm) {
            modalAuthForm.addEventListener('submit', (e) => {
                e.preventDefault();
                alert(`¡Proceso exitoso! Bienvenido/a.`);
                closeModal();
            });
        }
    };

    const closeModal = () => {
        authModal.classList.add('hidden');
    };

    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);

    // Close modal on background click
    window.addEventListener('click', (e) => {
        if (e.target === authModal) closeModal();
    });

    /* STREAMING_CHUNK: Event Listeners for Auth Buttons... */
    document.querySelectorAll('.heroRegisterBtn, #mobileRegisterBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (mobileMenu) mobileMenu.classList.remove('active');
            openModal('register');
        });
    });

    document.querySelectorAll('.heroLoginBtn, #navLoginBtn, #mobileLoginBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (mobileMenu) mobileMenu.classList.remove('active');
            openModal('login');
        });
    });

    /* STREAMING_CHUNK: Contact Form Submission Handler... */
    if (contactForm) {
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();
            contactForm.reset();
            formAlert.classList.remove('hidden');

            setTimeout(() => {
                formAlert.classList.add('hidden');
            }, 4000);
        });
    }

    /* STREAMING_CHUNK: Footer Info Alerts... */
    if (disclaimerLink) {
        disclaimerLink.addEventListener('click', (e) => {
            e.preventDefault();
            alert('Deslinde de responsabilidad: Aplicación de gestión de turnos simulada.');
        });
    }

    if (privacyLink) {
        privacyLink.addEventListener('click', (e) => {
            e.preventDefault();
            alert('Políticas de privacidad: Tus datos están asegurados.');
        });
    }
});