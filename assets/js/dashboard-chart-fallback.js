(function () {
    'use strict';

    window.showUnavailableCharts = function () {
        document.querySelectorAll('.chart-wrapper').forEach(wrapper => {
            wrapper.classList.remove('is-loading');
            if (wrapper.querySelector('.chart-unavailable')) return;

            const notice = document.createElement('p');
            notice.className = 'chart-unavailable dashboard-state';
            notice.textContent = 'Charts are unavailable. Application counts and recent activity are still shown.';
            wrapper.append(notice);

            const canvas = wrapper.querySelector('canvas');
            if (canvas) canvas.hidden = true;
        });
    };
})();
