(function () {
    'use strict';

    const instances = new WeakMap();

    class TablePaginator {
        constructor(tbody) {
            this.tbody = tbody;
            this.pageSize = Math.max(1, Number(tbody.dataset.paginate || 10));
            this.page = 1;
            this.pendingRefresh = false;
            this.nav = document.createElement('nav');
            this.nav.className = 'table-pagination';
            this.nav.setAttribute('aria-label', tbody.dataset.paginationLabel || 'Table pages');

            const table = tbody.closest('table');
            const wrapper = table?.closest('.table-wrapper, .table-wrap, .table-container, .records-table-wrap');
            (wrapper || table)?.insertAdjacentElement('afterend', this.nav);

            this.nav.addEventListener('click', event => {
                const button = event.target.closest('[data-table-page]');
                if (!button || button.disabled) return;
                const requestedPage = button.dataset.tablePage;
                const pageCount = this.pageCount();
                if (requestedPage === 'previous') this.page = Math.max(1, this.page - 1);
                else if (requestedPage === 'next') this.page = Math.min(pageCount, this.page + 1);
                else this.page = Math.min(pageCount, Math.max(1, Number(requestedPage)));
                this.render();
                this.nav.querySelector(`[data-table-page="${requestedPage}"]`)?.focus();
            });

            this.observer = new MutationObserver(mutations => {
                const filtered = mutations.some(mutation => mutation.type === 'attributes');
                this.scheduleRefresh(filtered);
            });
            this.observer.observe(tbody, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'hidden'] });
            this.render();
        }

        allRows() {
            return [...this.tbody.children].filter(row => row.tagName === 'TR');
        }

        eligibleRows() {
            return this.allRows().filter(row => {
                if (row.hidden || row.style.display === 'none' || row.dataset.paginationIgnore === 'true') return false;
                const cells = row.querySelectorAll('td');
                return !(cells.length === 1 && cells[0].hasAttribute('colspan'));
            });
        }

        pageCount() {
            return Math.max(1, Math.ceil(this.eligibleRows().length / this.pageSize));
        }

        scheduleRefresh(resetPage) {
            if (resetPage) this.page = 1;
            if (this.pendingRefresh) return;
            this.pendingRefresh = true;
            requestAnimationFrame(() => {
                this.pendingRefresh = false;
                this.render();
            });
        }

        render() {
            this.allRows().forEach(row => row.classList.remove('table-pagination__hidden'));
            const rows = this.eligibleRows();
            const pageCount = Math.max(1, Math.ceil(rows.length / this.pageSize));
            this.page = Math.min(this.page, pageCount);
            const start = (this.page - 1) * this.pageSize;
            const end = Math.min(start + this.pageSize, rows.length);
            rows.forEach((row, index) => row.classList.toggle('table-pagination__hidden', index < start || index >= end));

            if (rows.length <= this.pageSize) {
                this.nav.hidden = true;
                this.nav.innerHTML = '';
                return;
            }

            const firstPage = Math.max(1, Math.min(this.page - 2, pageCount - 4));
            const lastPage = Math.min(pageCount, firstPage + 4);
            let numberedPages = '';
            for (let pageNumber = firstPage; pageNumber <= lastPage; pageNumber += 1) {
                numberedPages += `<button type="button" class="table-pagination__page${pageNumber === this.page ? ' is-current' : ''}" data-table-page="${pageNumber}" ${pageNumber === this.page ? 'aria-current="page"' : ''}>${pageNumber}</button>`;
            }
            this.nav.hidden = false;
            this.nav.innerHTML = `<div class="table-pagination__summary">Showing ${start + 1}&ndash;${end} of ${rows.length}</div>
                <div class="table-pagination__controls">
                    <button type="button" class="table-pagination__page table-pagination__direction" data-table-page="previous" ${this.page === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left" aria-hidden="true"></i><span>Previous</span></button>
                    ${numberedPages}
                    <button type="button" class="table-pagination__page table-pagination__direction" data-table-page="next" ${this.page === pageCount ? 'disabled' : ''}><span>Next</span><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                </div>`;
        }
    }

    function initialize(root = document) {
        root.querySelectorAll('tbody[data-paginate]').forEach(tbody => {
            if (!instances.has(tbody)) instances.set(tbody, new TablePaginator(tbody));
        });
    }

    window.refreshTablePagination = target => {
        const tbody = typeof target === 'string' ? document.querySelector(target) : target;
        const instance = tbody ? instances.get(tbody) : null;
        instance?.scheduleRefresh(true);
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initialize(), { once: true });
    else initialize();
})();
