(function(){
 'use strict';
 const btn=document.getElementById('mobileMenuBtn'),sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebarOverlay');
 function closeMenu(){if(sidebar)sidebar.classList.remove('is-open');if(overlay)overlay.classList.remove('show');}
 if(btn)btn.addEventListener('click',()=>{if(sidebar)sidebar.classList.toggle('is-open');if(overlay)overlay.classList.toggle('show');});
 if(overlay)overlay.addEventListener('click',closeMenu);
 if(sidebar)sidebar.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
 const pkg=document.getElementById('package'),weight=document.getElementById('weight'),dateIn=document.getElementById('dateIn'),deadline=document.getElementById('deadlinePreview'),total=document.getElementById('totalPreview'),ratePreview=document.getElementById('ratePreview');
 const rupiah=n=>'Rp '+new Intl.NumberFormat('id-ID',{maximumFractionDigits:0}).format(n);
 function update(){if(!pkg||!weight)return;const opt=pkg.options[pkg.selectedIndex],rate=Number(opt.dataset.rate||7000),hours=Number(opt.dataset.hours||48),kg=Number(weight.value||0);if(total)total.textContent=rupiah(Math.round(rate*kg));if(ratePreview)ratePreview.textContent=rupiah(rate)+' per kg';if(dateIn&&dateIn.value&&deadline){const d=new Date(dateIn.value);if(!Number.isNaN(d.getTime())){d.setHours(d.getHours()+hours);deadline.value=new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,16);}}}
 if(pkg)pkg.addEventListener('change',update);if(weight)weight.addEventListener('input',update);if(dateIn)dateIn.addEventListener('change',update);update();
})();

// ==========================================
// TOAST NOTIFICATION CONTROLLER (TOP-RIGHT)
// ==========================================
window.initAppToastItem = function(toast, duration = 4500) {
    if (!toast || toast.hasAttribute('data-toast-initialized')) return;
    toast.setAttribute('data-toast-initialized', 'true');

    const progressBar = toast.querySelector('.app-toast-progress-bar');
    const closeBtn = toast.querySelector('.app-toast-close');

    let startTime = Date.now();
    let remainingTime = duration;
    let timerId = null;
    let isPaused = false;

    function dismissToast() {
        if (toast.classList.contains('is-hiding')) return;
        toast.classList.add('is-hiding');
        if (timerId) clearTimeout(timerId);
        toast.addEventListener('animationend', function() {
            toast.remove();
        }, { once: true });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dismissToast();
        });
    }

    function startTimer(timeLeft) {
        if (progressBar) {
            progressBar.style.transition = `width ${timeLeft}ms linear`;
            progressBar.style.width = '0%';
        }
        startTime = Date.now();
        timerId = setTimeout(dismissToast, timeLeft);
    }

    function pauseTimer() {
        if (isPaused) return;
        isPaused = true;
        clearTimeout(timerId);
        const elapsed = Date.now() - startTime;
        remainingTime = Math.max(0, remainingTime - elapsed);
        if (progressBar) {
            const computedWidth = window.getComputedStyle(progressBar).width;
            progressBar.style.transition = 'none';
            progressBar.style.width = computedWidth;
        }
    }

    function resumeTimer() {
        if (!isPaused) return;
        isPaused = false;
        startTimer(remainingTime);
    }

    toast.addEventListener('mouseenter', pauseTimer);
    toast.addEventListener('mouseleave', resumeTimer);

    requestAnimationFrame(function() {
        startTimer(duration);
    });
};

window.initAppToasts = function() {
    const toasts = document.querySelectorAll('.app-toast:not([data-toast-initialized])');
    toasts.forEach(function(t) {
        window.initAppToastItem(t, 4500);
    });
};

window.createAppToast = function(message, type = 'success', title = '', duration = 4500) {
    let container = document.getElementById('appToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'appToastContainer';
        container.className = 'app-toast-container';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
    }

    const typeIcons = {
        success: 'bi-check-circle-fill',
        danger: 'bi-x-circle-fill',
        error: 'bi-x-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info: 'bi-info-circle-fill'
    };

    const typeTitles = {
        success: 'Berhasil',
        danger: 'Terjadi Kesalahan',
        error: 'Terjadi Kesalahan',
        warning: 'Peringatan',
        info: 'Informasi'
    };

    const safeType = typeIcons[type] ? type : 'info';
    const iconClass = typeIcons[safeType];
    const finalTitle = title || typeTitles[safeType];

    const toast = document.createElement('div');
    toast.className = `app-toast app-toast-${safeType}`;
    toast.setAttribute('role', 'alert');

    toast.innerHTML = `
        <div class="app-toast-icon">
            <i class="bi ${iconClass}"></i>
        </div>
        <div class="app-toast-content">
            <div class="app-toast-title">${finalTitle}</div>
            <div class="app-toast-message">${message}</div>
        </div>
        <button type="button" class="app-toast-close" aria-label="Tutup notifikasi">
            <i class="bi bi-x"></i>
        </button>
        <div class="app-toast-progress">
            <div class="app-toast-progress-bar"></div>
        </div>
    `;

    container.appendChild(toast);
    window.initAppToastItem(toast, duration);
    return toast;
};

// Inisialisasi otomatis saat DOM siap
document.addEventListener('DOMContentLoaded', function() {
    window.initAppToasts();

    // Cegah double submit pada modal konfirmasi aksi
    document.querySelectorAll('.confirm-modal-custom form').forEach(function(form) {
        form.addEventListener('submit', function() {
            const btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memproses...';
            }
        });
    });

    // Loading state pada tombol logout modal
    const logoutBtn = document.querySelector('#modalKonfirmasiLogout a.btn-danger');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            if (!this.classList.contains('disabled')) {
                this.classList.add('disabled');
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Mengeluarkan...';
            }
        });
    }
});
