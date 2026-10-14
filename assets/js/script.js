(function () {
    'use strict';

    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const tableBody = document.getElementById('ordersTableBody');
    const emptyRow = document.getElementById('emptyTableRow');
    const visibleCount = document.getElementById('visibleCount');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const newOrderForm = document.getElementById('newOrderForm');
    const orderPackage = document.getElementById('orderPackage');
    const orderWeight = document.getElementById('orderWeight');
    const dateIn = document.getElementById('dateIn');
    const deadlinePreview = document.getElementById('deadlinePreview');
    const totalPreview = document.getElementById('totalPreview');
    const ratePreview = document.getElementById('ratePreview');
    const deadlineHint = document.getElementById('deadlineHint');
    const receiptModalElement = document.getElementById('receiptModal');
    const toastElement = document.getElementById('demoToast');
    let receiptData = null;

    function rupiah(value) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value).replace('IDR', 'Rp').replace(/\s+/g, ' ').trim();
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character];
        });
    }

    function formatDateTime(value) {
        if (!value) return '-';
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(date);
    }

    function formatDeadlineLabel(value) {
        if (!value) return '-';
        const deadline = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(deadline.getTime())) return value;
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        const deadlineDay = new Date(deadline.getFullYear(), deadline.getMonth(), deadline.getDate());
        const time = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(deadline);
        if (deadlineDay.getTime() === today.getTime()) return 'Hari ini, ' + time;
        if (deadlineDay.getTime() === tomorrow.getTime()) return 'Besok, ' + time;
        return formatDateTime(value);
    }

    function applyFilters() {
        if (!tableBody) return;
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const selectedStatus = statusFilter ? statusFilter.value : 'all';
        const rows = Array.from(tableBody.querySelectorAll('tr[data-order-row]'));
        let visible = 0;

        rows.forEach(function (row) {
            const searchable = (row.dataset.code + ' ' + row.dataset.customer + ' ' + row.textContent).toLowerCase();
            const matchQuery = !query || searchable.includes(query);
            const matchStatus = selectedStatus === 'all' || row.dataset.status === selectedStatus;
            const show = matchQuery && matchStatus;
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        if (emptyRow) emptyRow.classList.toggle('d-none', visible !== 0);
        if (visibleCount) visibleCount.textContent = visible + ' order';
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);

    function closeMobileSidebar() {
        if (sidebar) sidebar.classList.remove('is-open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
    }

    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', function () {
        if (!sidebar || !sidebarOverlay) return;
        sidebar.classList.toggle('is-open');
        sidebarOverlay.classList.toggle('show');
    });
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeMobileSidebar);
    if (sidebar) sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMobileSidebar);
    });

    function getSelectedPackage() {
        if (!orderPackage || !orderPackage.selectedOptions.length) return { name: 'Reguler', rate: 7000, hours: 48 };
        const option = orderPackage.selectedOptions[0];
        return {
            name: option.value,
            rate: Number(option.dataset.rate || 7000),
            hours: Number(option.dataset.hours || 48)
        };
    }

    function updateOrderPreview() {
        if (!orderPackage || !orderWeight || !dateIn) return;
        const packageData = getSelectedPackage();
        const weight = Math.max(0, Number(orderWeight.value || 0));
        if (ratePreview) ratePreview.textContent = rupiah(packageData.rate) + ' per kg';
        if (totalPreview) totalPreview.textContent = rupiah(Math.round(weight * packageData.rate));

        if (dateIn.value && deadlinePreview) {
            const start = new Date(dateIn.value);
            if (!Number.isNaN(start.getTime())) {
                start.setHours(start.getHours() + packageData.hours);
                const localDateTime = new Date(start.getTime() - start.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                deadlinePreview.value = localDateTime;
                if (deadlineHint) deadlineHint.textContent = 'Estimasi ' + packageData.hours + ' jam setelah tanggal masuk (contoh aturan layanan).';
            }
        }
    }

    function setDefaultDate() {
        if (!dateIn || dateIn.value) return;
        const now = new Date();
        dateIn.value = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        updateOrderPreview();
    }

    if (orderPackage) orderPackage.addEventListener('change', updateOrderPreview);
    if (orderWeight) orderWeight.addEventListener('input', updateOrderPreview);
    if (dateIn) dateIn.addEventListener('input', updateOrderPreview);
    const orderModalElement = document.getElementById('orderModal');
    if (orderModalElement) orderModalElement.addEventListener('show.bs.modal', setDefaultDate);
    setDefaultDate();

    function createStatusBadge(status) {
        const statusMap = {
            waiting: ['Menunggu', 'status-waiting'],
            washing: ['Dicuci', 'status-process'],
            drying: ['Dikeringkan', 'status-process'],
            ironing: ['Disetrika', 'status-process'],
            ready: ['Siap Diambil', 'status-ready'],
            completed: ['Selesai', 'status-completed'],
            cancelled: ['Dibatalkan', 'status-cancelled']
        };
        const current = statusMap[status] || statusMap.waiting;
        return '<span class="status-badge ' + current[1] + '"><span class="status-dot"></span>' + current[0] + '</span>';
    }

    function createOrderRow(order) {
        const initials = order.customer.trim().split(/\s+/).slice(0, 2).map(function (part) { return part.charAt(0); }).join('').toUpperCase();
        const packageClass = order.package.toLowerCase() === 'express' ? 'package-express' : 'package-regular';
        const packageData = getSelectedPackage();
        const rate = order.package === 'Express' ? 14000 : 7000;
        const tr = document.createElement('tr');
        tr.dataset.orderRow = '';
        tr.dataset.code = order.code;
        tr.dataset.customer = order.customer;
        tr.dataset.status = 'waiting';
        tr.dataset.deadline = order.deadline;
        tr.dataset.package = order.package;
        tr.dataset.weight = String(order.weight);
        tr.dataset.total = String(Math.round(Number(order.weight) * rate));
        tr.dataset.phone = order.phone;
        tr.dataset.dateIn = order.dateIn;
        tr.innerHTML = `
            <td><span class="order-code">${escapeHtml(order.code)}</span><small class="cell-subtext">Masuk ${escapeHtml(order.dateIn.slice(11, 16).replace(':', '.'))}</small></td>
            <td><div class="customer-cell"><span class="customer-avatar">${escapeHtml(initials)}</span><span><strong>${escapeHtml(order.customer)}</strong><small>${escapeHtml(order.phone || '-')}</small></span></div></td>
            <td><span class="package-pill ${packageClass}">${escapeHtml(order.package)}</span></td>
            <td><strong>${escapeHtml(String(order.weight).replace('.', ','))} kg</strong></td>
            <td><span class="deadline-text">${escapeHtml(formatDeadlineLabel(order.deadline))}</span><small class="cell-subtext">${escapeHtml(formatDateTime(order.deadline).split(',')[0])}</small></td>
            <td><strong class="price-text">${escapeHtml(rupiah(Math.round(Number(order.weight) * rate)))}</strong></td>
            <td>${createStatusBadge('waiting')}</td>
            <td class="text-end"><button class="btn btn-sm btn-receipt js-receipt" type="button"><i class="bi bi-receipt me-1"></i>Struk</button></td>`;
        return tr;
    }

    function sortRowsByDeadline() {
        if (!tableBody) return;
        const rows = Array.from(tableBody.querySelectorAll('tr[data-order-row]'));
        rows.sort(function (a, b) {
            const doneA = ['completed', 'cancelled'].includes(a.dataset.status);
            const doneB = ['completed', 'cancelled'].includes(b.dataset.status);
            if (doneA !== doneB) return doneA ? 1 : -1;
            return new Date(a.dataset.deadline.replace(' ', 'T')) - new Date(b.dataset.deadline.replace(' ', 'T'));
        });
        rows.forEach(function (row) { tableBody.insertBefore(row, emptyRow); });
    }

    if (newOrderForm) newOrderForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!newOrderForm.reportValidity()) return;
        const packageData = getSelectedPackage();
        const weight = Number(orderWeight.value);
        const dateValue = dateIn.value;
        const deadlineValue = deadlinePreview.value;
        if (!Number.isFinite(weight) || weight <= 0 || !dateValue || !deadlineValue) return;

        const dateForCode = new Date(dateValue);
        const dayCode = dateForCode.getFullYear() + String(dateForCode.getMonth() + 1).padStart(2, '0') + String(dateForCode.getDate()).padStart(2, '0');
        const count = tableBody ? tableBody.querySelectorAll('tr[data-order-row]').length + 1 : 1;
        const code = 'LD-' + dayCode + '-' + String(count).padStart(3, '0');
        const order = {
            code: code,
            customer: document.getElementById('customerName').value.trim(),
            phone: document.getElementById('customerPhone').value.trim(),
            package: packageData.name,
            weight: weight,
            dateIn: dateValue.replace('T', ' ') + ':00',
            deadline: deadlineValue.replace('T', ' ') + ':00'
        };
        const newRow = createOrderRow(order);
        if (emptyRow && emptyRow.parentNode) tableBody.insertBefore(newRow, emptyRow);
        else tableBody.appendChild(newRow);
        sortRowsByDeadline();
        applyFilters();

        const modal = bootstrap.Modal.getInstance(orderModalElement);
        if (modal) modal.hide();
        newOrderForm.reset();
        deadlinePreview.value = '';
        setDefaultDate();
        showToast('Order demo ditambahkan ke tabel. Belum tersimpan ke database.');
    });

    function showToast(message) {
        const messageElement = document.getElementById('toastMessage');
        if (messageElement) messageElement.textContent = message;
        if (toastElement && window.bootstrap) bootstrap.Toast.getOrCreateInstance(toastElement, { delay: 4000 }).show();
    }

    function openReceiptFromRow(row) {
        receiptData = {
            code: row.dataset.code || '-',
            customer: row.dataset.customer || '-',
            package: row.dataset.package || '-',
            weight: row.dataset.weight || '0',
            total: row.dataset.total || '0',
            phone: row.dataset.phone || '-',
            dateIn: row.dataset.dateIn || '-',
            deadline: row.dataset.deadline || '-'
        };
        document.getElementById('receiptCode').textContent = receiptData.code;
        document.getElementById('receiptDateIn').textContent = formatDateTime(receiptData.dateIn);
        document.getElementById('receiptCustomer').textContent = receiptData.customer;
        document.getElementById('receiptPhone').textContent = receiptData.phone || '-';
        document.getElementById('receiptPackage').textContent = receiptData.package;
        document.getElementById('receiptWeight').textContent = String(receiptData.weight).replace('.', ',') + ' kg';
        document.getElementById('receiptDeadline').textContent = formatDateTime(receiptData.deadline);
        document.getElementById('receiptTotal').textContent = rupiah(Number(receiptData.total));
        bootstrap.Modal.getOrCreateInstance(receiptModalElement).show();
    }

    if (tableBody) tableBody.addEventListener('click', function (event) {
        const button = event.target.closest('.js-receipt');
        if (!button) return;
        const row = button.closest('tr[data-order-row]');
        if (row) openReceiptFromRow(row);
    });

    const printReceiptBtn = document.getElementById('printReceiptBtn');
    if (printReceiptBtn) printReceiptBtn.addEventListener('click', function () {
        const content = document.getElementById('receiptContent');
        if (!content) return;
        const printWindow = window.open('', '_blank', 'width=480,height=720');
        if (!printWindow) {
            showToast('Izinkan pop-up browser untuk mencetak struk.');
            return;
        }
        printWindow.document.write(`<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Struk Laundry</title><style>
            *{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#334155;margin:0;padding:24px;background:#fff}.paper{max-width:340px;margin:0 auto;border:1px dashed #cbd5e1;padding:24px 20px;text-align:center}.mark{font-size:26px;color:#267b72;margin-bottom:8px}.name{font-size:19px;font-weight:700;margin:0}.tag{font-size:11px;color:#64748b;margin:5px 0 16px}.divider{border-top:1px dashed #cbd5e1;margin:14px 0}.line{display:flex;justify-content:space-between;gap:12px;text-align:left;font-size:11px;margin:10px 0}.line span{color:#64748b}.line strong{text-align:right;max-width:60%;overflow-wrap:anywhere}.total{display:flex;justify-content:space-between;gap:10px;color:#267b72;font-size:13px;font-weight:700}.thanks{font-size:10px;line-height:1.7;color:#64748b;margin-top:18px}@media print{body{padding:0}.paper{border:0}}
            </style></head><body><div class="paper"><div class="mark">◆</div><h1 class="name">LaundryKu</h1><p class="tag">Bukti transaksi laundry</p><div class="divider"></div>
            <div class="line"><span>No. transaksi</span><strong>${escapeHtml(receiptData ? receiptData.code : '')}</strong></div>
            <div class="line"><span>Tanggal masuk</span><strong>${escapeHtml(receiptData ? formatDateTime(receiptData.dateIn) : '')}</strong></div>
            <div class="line"><span>Pelanggan</span><strong>${escapeHtml(receiptData ? receiptData.customer : '')}</strong></div>
            <div class="line"><span>WhatsApp</span><strong>${escapeHtml(receiptData ? receiptData.phone : '')}</strong></div><div class="divider"></div>
            <div class="line"><span>Paket</span><strong>${escapeHtml(receiptData ? receiptData.package : '')}</strong></div>
            <div class="line"><span>Berat pakaian</span><strong>${escapeHtml(receiptData ? String(receiptData.weight).replace('.', ',') + ' kg' : '')}</strong></div>
            <div class="line"><span>Deadline</span><strong>${escapeHtml(receiptData ? formatDateTime(receiptData.deadline) : '')}</strong></div><div class="divider"></div>
            <div class="total"><span>Total biaya</span><strong>${escapeHtml(receiptData ? rupiah(Number(receiptData.total)) : '')}</strong></div>
            <p class="thanks">Simpan struk ini untuk pengecekan order.<br>Terima kasih sudah menggunakan layanan kami.</p></div><script>window.onload=function(){window.print();};<\/script></body></html>`);
        printWindow.document.close();
    });

    applyFilters();
})();
