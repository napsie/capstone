(function () {
    'use strict';

    window.initializeCalendar = function initializeCalendar() {
        const monthYear = document.getElementById('month-year');
        const days = document.getElementById('calendar-days');
        const previous = document.getElementById('prev-month');
        const next = document.getElementById('next-month');
        const todayButton = document.getElementById('calendar-today');
        const selection = document.getElementById('calendar-selection');
        if (!monthYear || !days || !previous || !next) return;

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        let visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        let selectedDate = new Date(today);

        const sameDay = (a, b) => a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

        function announceSelection() {
            if (selection) {
                selection.textContent = selectedDate.toLocaleDateString('en-PH', {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                });
            }
        }

        function selectDate(date) {
            selectedDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            visibleMonth = new Date(date.getFullYear(), date.getMonth(), 1);
            render();
        }

        function render() {
            const year = visibleMonth.getFullYear();
            const month = visibleMonth.getMonth();
            const firstGridDate = new Date(year, month, 1 - new Date(year, month, 1).getDay());
            monthYear.textContent = visibleMonth.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
            monthYear.setAttribute('aria-label', `Calendar for ${monthYear.textContent}`);
            days.replaceChildren();

            for (let week = 0; week < 6; week += 1) {
                const row = document.createElement('tr');
                for (let weekday = 0; weekday < 7; weekday += 1) {
                    const date = new Date(firstGridDate);
                    date.setDate(firstGridDate.getDate() + (week * 7) + weekday);
                    const cell = document.createElement('td');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'calendar-day';
                    button.textContent = String(date.getDate());
                    button.tabIndex = sameDay(date, selectedDate) ? 0 : -1;
                    button.setAttribute('aria-label', date.toLocaleDateString('en-PH', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                    }));
                    if (date.getMonth() !== month) cell.classList.add('inactive');
                    if (weekday === 0 || weekday === 6) cell.classList.add('weekend');
                    if (sameDay(date, today)) {
                        cell.classList.add('today');
                        button.setAttribute('aria-current', 'date');
                    }
                    if (sameDay(date, selectedDate)) {
                        cell.classList.add('selected');
                        button.setAttribute('aria-pressed', 'true');
                    } else {
                        button.setAttribute('aria-pressed', 'false');
                    }
                    button.addEventListener('click', () => selectDate(date));
                    button.addEventListener('keydown', event => {
                        const offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
                        if (!(event.key in offsets)) return;
                        event.preventDefault();
                        const target = new Date(date);
                        target.setDate(target.getDate() + offsets[event.key]);
                        selectDate(target);
                        requestAnimationFrame(() => days.querySelector('.selected .calendar-day')?.focus());
                    });
                    cell.appendChild(button);
                    row.appendChild(cell);
                }
                days.appendChild(row);
            }
            announceSelection();
        }

        previous.addEventListener('click', () => {
            visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
            render();
        });
        next.addEventListener('click', () => {
            visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
            render();
        });
        todayButton?.addEventListener('click', () => selectDate(today));
        render();
    };
}());
