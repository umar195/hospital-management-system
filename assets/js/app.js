/* =========================================================================
   Hospital Management System - shared front-end helpers
   ========================================================================= */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // ------------------------------------------------ mobile sidebar
        var toggle = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('appSidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (toggle && sidebar) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                if (backdrop) { backdrop.classList.toggle('show'); }
            });
        }
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
            });
        }

        // ------------------------------------------------ confirm actions
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                if (!window.confirm(el.getAttribute('data-confirm'))) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
        });

        // ------------------------------------------------ auto dismiss alerts
        document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
            window.setTimeout(function () {
                if (window.bootstrap && bootstrap.Alert) {
                    var instance = bootstrap.Alert.getOrCreateInstance(alert);
                    instance.close();
                }
            }, 6000);
        });

        // ------------------------------------------------ tooltips
        if (window.bootstrap && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }

        // ------------------------------------------------ table quick filter
        document.querySelectorAll('[data-table-filter]').forEach(function (input) {
            input.addEventListener('keyup', function () {
                var target = document.querySelector(input.getAttribute('data-table-filter'));
                if (!target) { return; }
                var term = input.value.toLowerCase();
                target.querySelectorAll('tbody tr').forEach(function (row) {
                    row.style.display = row.textContent.toLowerCase().indexOf(term) > -1 ? '' : 'none';
                });
            });
        });

        // ------------------------------------------------ date of birth -> age
        var dob = document.getElementById('date_of_birth');
        var age = document.getElementById('age');
        if (dob && age) {
            dob.addEventListener('change', function () {
                if (!dob.value) { return; }
                var birth = new Date(dob.value);
                var now = new Date();
                var years = now.getFullYear() - birth.getFullYear();
                var m = now.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) { years--; }
                if (years >= 0 && years < 130) { age.value = years; }
            });
        }

        // ------------------------------------------------ print buttons
        document.querySelectorAll('[data-print]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                window.print();
            });
        });
    });

    // ---------------------------------------------------- helpers
    window.HMS = {
        money: function (value) {
            return (parseFloat(value) || 0).toFixed(2);
        },
        /** Live patient search. Calls ajax/search_patients.php */
        patientSearch: function (inputEl, resultsEl, onSelect) {
            var timer = null;
            inputEl.addEventListener('keyup', function () {
                window.clearTimeout(timer);
                var term = inputEl.value.trim();
                if (term.length < 2) {
                    resultsEl.innerHTML = '';
                    return;
                }
                timer = window.setTimeout(function () {
                    fetch(window.BASE_URL + '/ajax/search_patients.php?q=' + encodeURIComponent(term))
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data.length) {
                                resultsEl.innerHTML = '<div class="list-group-item text-muted small">No patient found. Register a new one below.</div>';
                                return;
                            }
                            resultsEl.innerHTML = data.map(function (p) {
                                return '<button type="button" class="list-group-item list-group-item-action search-result-item" ' +
                                    'data-id="' + p.id + '" data-name="' + p.full_name + '" data-code="' + p.patient_id + '" ' +
                                    'data-gender="' + p.gender + '" data-age="' + (p.age || '') + '" data-phone="' + (p.phone || '') + '">' +
                                    '<div class="d-flex justify-content-between"><strong>' + p.full_name + '</strong>' +
                                    '<span class="badge bg-light text-dark">' + p.patient_id + '</span></div>' +
                                    '<small class="text-muted">' + p.gender + (p.age ? ' / ' + p.age + 'y' : '') + ' &middot; ' + (p.phone || 'no phone') + '</small>' +
                                    '</button>';
                            }).join('');
                            resultsEl.querySelectorAll('.search-result-item').forEach(function (item) {
                                item.addEventListener('click', function () {
                                    onSelect({
                                        id: item.dataset.id,
                                        full_name: item.dataset.name,
                                        patient_id: item.dataset.code,
                                        gender: item.dataset.gender,
                                        age: item.dataset.age,
                                        phone: item.dataset.phone
                                    });
                                });
                            });
                        })
                        .catch(function () {
                            resultsEl.innerHTML = '<div class="list-group-item text-danger small">Search failed.</div>';
                        });
                }, 250);
            });
        }
    };
})();
