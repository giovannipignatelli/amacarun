/**
 * AmacarUN Race Manager - Frontend Public JavaScript
 * Gestisce le interazioni frontend per shortcodes e componenti pubblici
 */

(function($) {
    'use strict';
    
    // Namespace globale per frontend
    window.AmacarUNPublic = window.AmacarUNPublic || {};
    
    const AmacarUNPublic = {
        
        /**
         * Inizializzazione generale
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
            this.setupLazyLoading();
        },
        
        /**
         * Binding eventi generali
         */
        bindEvents: function() {
            // Live search
            $(document).on('keyup', '.amacarun-live-search-input', this.handleLiveSearch);
            
            // Pagination AJAX
            $(document).on('click', '.amacarun-pagination a', this.handlePagination);
            
            // Filter changes
            $(document).on('change', '.amacarun-filters select', this.handleFilterChange);
            
            // Card interactions
            $(document).on('click', '.amacarun-participant-card', this.handleCardClick);
            
            // Chart initialization
            if (typeof Chart !== 'undefined') {
                this.initCharts();
            }
            
            // Scroll animations
            $(window).on('scroll', this.handleScrollAnimations);
            
            // Responsive tables
            this.makeTablesResponsive();
        },
        
        /**
         * Inizializza componenti
         */
        initComponents: function() {
            // Inizializza ricerca live
            this.initLiveSearch();
            
            // Anima contatori statistiche
            this.animateCounters();
            
            // Inizializza tooltips se disponibili
            if ($.fn.tooltip) {
                $('[data-tooltip]').tooltip({
                    placement: 'top',
                    trigger: 'hover'
                });
            }
            
            // Caricamento immagini lazy
            this.initLazyImages();
            
            // Inizializza filtri avanzati
            this.initAdvancedFilters();
        },
        
        /**
         * Live Search Handler
         */
        handleLiveSearch: function() {
            const $input = $(this);
            const $container = $input.closest('.amacarun-live-search');
            const $results = $container.find('.amacarun-search-results');
            const $loading = $container.find('.amacarun-search-loading');
            const $count = $container.find('.amacarun-results-count');
            
            const query = $input.val().trim();
            const eventId = $container.data('event-id');
            const minChars = parseInt($container.data('min-chars') || 2);
            
            // Clear previous timeout
            clearTimeout($input.data('search-timeout'));
            
            if (query.length < minChars) {
                $results.empty();
                $count.hide();
                $loading.hide();
                return;
            }
            
            // Show loading
            $loading.show();
            
            // Set new timeout
            $input.data('search-timeout', setTimeout(function() {
                AmacarUNPublic.performLiveSearch(query, eventId, $container);
            }, 300));
        },
        
        /**
         * Esegui ricerca live
         */
        performLiveSearch: function(query, eventId, $container) {
            const $results = $container.find('.amacarun-search-results');
            const $loading = $container.find('.amacarun-search-loading');
            const $count = $container.find('.amacarun-results-count');
            
            $.ajax({
                url: amacarun_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'amacarun_public_search',
                    query: query,
                    event_id: eventId,
                    nonce: amacarun_public.nonce
                },
                success: function(response) {
                    $loading.hide();
                    
                    if (response.success) {
                        const results = response.data.results;
                        const count = response.data.count;
                        const message = response.data.message;
                        
                        // Update count
                        if (count > 0) {
                            $count.text(message).show();
                        } else {
                            $count.hide();
                        }
                        
                        // Display results
                        AmacarUNPublic.displaySearchResults(results, $results);
                    } else {
                        $results.html(`<div class="amacarun-no-results">${amacarun_public.strings.no_results}</div>`);
                        $count.hide();
                    }
                },
                error: function() {
                    $loading.hide();
                    $results.html(`<div class="amacarun-no-results">Errore nella ricerca</div>`);
                }
            });
        },
        
        /**
         * Mostra risultati ricerca
         */
        displaySearchResults: function(results, $container) {
            let html = '';
            
            if (results.length === 0) {
                html = `<div class="amacarun-no-results">${amacarun_public.strings.no_results}</div>`;
            } else {
                results.forEach(function(participant) {
                    const bibDisplay = participant.bib_number ? '#' + participant.bib_number : 'N/A';
                    const statusClass = 'amacarun-status-' + participant.status;
                    const typeLabel = participant.type === 'adult' ? 'Adulto' : 'Bambino';
                    
                    html += `
                        <div class="amacarun-search-result-item ${statusClass}">
                            <div class="amacarun-search-result-bib">${bibDisplay}</div>
                            <div class="amacarun-search-result-name">${participant.name}</div>
                            <div class="amacarun-search-result-meta">
                                <span class="result-type">${typeLabel}</span>
                                <span class="result-status">${participant.status}</span>
                                ${participant.distance ? '<span class="result-distance">' + participant.distance + '</span>' : ''}
                            </div>
                        </div>
                    `;
                });
            }
            
            $container.html(html).addClass('amacarun-fade-in');
        },
        
        /**
         * Inizializza Live Search
         */
        initLiveSearch: function() {
            $('.amacarun-live-search').each(function() {
                const $container = $(this);
                const $input = $container.find('.amacarun-live-search-input');
                
                // Focus effect
                $input.on('focus', function() {
                    $container.addClass('focused');
                }).on('blur', function() {
                    setTimeout(function() {
                        $container.removeClass('focused');
                    }, 200);
                });
            });
        },
        
        /**
         * Gestisce paginazione AJAX
         */
        handlePagination: function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const href = $link.attr('href');
            const $container = $link.closest('.amacarun-participants-container');
            
            // Show loading
            $container.addClass('loading');
            
            // Load new page content via AJAX
            $.get(href, function(data) {
                const $newContent = $(data).find('.amacarun-participants-container');
                
                if ($newContent.length) {
                    $container.html($newContent.html());
                    
                    // Scroll to top of results
                    $('html, body').animate({
                        scrollTop: $container.offset().top - 20
                    }, 500);
                    
                    // Re-initialize components
                    AmacarUNPublic.animateCounters();
                }
            }).fail(function() {
                // Fallback to normal page load
                window.location.href = href;
            }).always(function() {
                $container.removeClass('loading');
            });
        },
        
        /**
         * Gestisce cambi filtri
         */
        handleFilterChange: function() {
            const $form = $(this).closest('form');
            
            // Auto-submit form dopo breve delay
            clearTimeout($form.data('submit-timeout'));
            $form.data('submit-timeout', setTimeout(function() {
                $form.submit();
            }, 500));
        },
        
        /**
         * Gestisce click su card partecipanti
         */
        handleCardClick: function() {
            const $card = $(this);
            
            // Toggle expanded state
            $card.toggleClass('expanded');
            
            // Animate card expansion
            if ($card.hasClass('expanded')) {
                $card.find('.amacarun-card-details').slideDown(300);
            } else {
                $card.find('.amacarun-card-details').slideUp(300);
            }
        },
        
        /**
         * Anima contatori statistiche
         */
        animateCounters: function() {
            $('.amacarun-stat-number').each(function() {
                const $counter = $(this);
                
                // Skip if already animated
                if ($counter.data('animated')) return;
                
                const target = parseInt($counter.text()) || 0;
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;
                
                $counter.data('animated', true);
                
                const timer = setInterval(function() {
                    current += increment;
                    
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    
                    $counter.text(Math.floor(current));
                }, 16);
            });
        },
        
        /**
         * Inizializza grafici Chart.js
         */
        initCharts: function() {
            $('[id*="amacarun-stats-chart"]').each(function() {
                const $canvas = $(this);
                const statsData = JSON.parse($canvas.attr('data-stats') || '{}');
                
                if (Object.keys(statsData).length === 0) return;
                
                const ctx = this.getContext('2d');
                
                // Prepara dati per grafico a ciambella
                const chartData = {
                    labels: ['Adulti', 'Bambini'],
                    datasets: [{
                        data: [statsData.adults || 0, statsData.children || 0],
                        backgroundColor: ['#c41e3a', '#f77f00'],
                        borderColor: ['#a11729', '#e56b00'],
                        borderWidth: 2
                    }]
                };
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    font: {
                                        size: 14
                                    }
                                }
                            }
                        }
                    }
                });
            });
        },
        
        /**
         * Gestisce animazioni scroll
         */
        handleScrollAnimations: function() {
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();
            
            $('.amacarun-animate-on-scroll').each(function() {
                const $element = $(this);
                const elementTop = $element.offset().top;
                
                if (scrollTop + windowHeight > elementTop + 100 && !$element.hasClass('animated')) {
                    $element.addClass('animated amacarun-fade-in');
                }
            });
        },
        
        /**
         * Rende tabelle responsive
         */
        makeTablesResponsive: function() {
            $('.amacarun-participants-table').each(function() {
                const $table = $(this);
                const $container = $table.parent();
                
                if (!$container.hasClass('amacarun-table-container')) {
                    $table.wrap('<div class="amacarun-table-container"></div>');
                }
                
                // Add mobile headers
                $table.find('tbody tr').each(function() {
                    const $row = $(this);
                    
                    $row.find('td').each(function(index) {
                        const $cell = $(this);
                        const headerText = $table.find('thead th').eq(index).text();
                        
                        $cell.attr('data-label', headerText);
                    });
                });
            });
        },
        
        /**
         * Inizializza caricamento lazy
         */
        setupLazyLoading: function() {
            // Lazy load delle immagini se presenti
            $('img[data-src]').each(function() {
                const $img = $(this);
                
                // Intersection Observer se supportato
                if ('IntersectionObserver' in window) {
                    const observer = new IntersectionObserver(function(entries) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                const img = entry.target;
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                observer.unobserve(img);
                            }
                        });
                    });
                    
                    observer.observe(this);
                } else {
                    // Fallback per browser più vecchi
                    $img.attr('src', $img.data('src')).removeAttr('data-src');
                }
            });
        },
        
        /**
         * Inizializza immagini lazy
         */
        initLazyImages: function() {
            // Placeholder per immagini che non si caricano
            $('img').on('error', function() {
                const $img = $(this);
                if (!$img.hasClass('error-handled')) {
                    $img.addClass('error-handled');
                    $img.attr('src', 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"%3E%3Crect fill="%23ddd" width="100" height="100"/%3E%3Ctext y="50" x="50" text-anchor="middle" dy=".3em" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E');
                }
            });
        },
        
        /**
         * Inizializza filtri avanzati
         */
        initAdvancedFilters: function() {
            $('.amacarun-filters').each(function() {
                const $container = $(this);
                
                // Aggiungi pulsante reset se ci sono filtri attivi
                const $selects = $container.find('select');
                let hasActiveFilters = false;
                
                $selects.each(function() {
                    if ($(this).val() !== '') {
                        hasActiveFilters = true;
                    }
                });
                
                if (hasActiveFilters && !$container.find('.amacarun-reset-filters').length) {
                    const resetUrl = window.location.pathname + window.location.search.replace(/[?&]amacarun_[^&]+/g, '').replace(/^&/, '?');
                    $container.append(`<a href="${resetUrl}" class="amacarun-reset-filters">Reset Filtri</a>`);
                }
                
                // Anima filtri attivi
                $selects.each(function() {
                    if ($(this).val() !== '') {
                        $(this).addClass('has-value');
                    }
                });
            });
        },
        
        /**
         * Utilities per animazioni
         */
        Utils: {
            
            /**
             * Anima elemento con effetto typewriter
             */
            typewriterEffect: function($element, text, speed) {
                speed = speed || 50;
                let i = 0;
                
                $element.empty();
                
                function typeChar() {
                    if (i < text.length) {
                        $element.append(text.charAt(i));
                        i++;
                        setTimeout(typeChar, speed);
                    }
                }
                
                typeChar();
            },
            
            /**
             * Evidenzia testo in ricerca
             */
            highlightSearchTerm: function($container, searchTerm) {
                if (!searchTerm) return;
                
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                
                $container.find('.amacarun-search-result-name').each(function() {
                    const $element = $(this);
                    const originalText = $element.text();
                    const highlightedText = originalText.replace(regex, '<mark>$1</mark>');
                    $element.html(highlightedText);
                });
            },
            
            /**
             * Smooth scroll personalizzato
             */
            smoothScrollTo: function(target, duration) {
                duration = duration || 800;
                
                const $target = $(target);
                if (!$target.length) return;
                
                $('html, body').animate({
                    scrollTop: $target.offset().top - 20
                }, duration);
            },
            
            /**
             * Debounce function
             */
            debounce: function(func, wait, immediate) {
                let timeout;
                return function() {
                    const context = this, args = arguments;
                    const later = function() {
                        timeout = null;
                        if (!immediate) func.apply(context, args);
                    };
                    const callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func.apply(context, args);
                };
            },
            
            /**
             * Throttle function
             */
            throttle: function(func, limit) {
                let inThrottle;
                return function() {
                    const args = arguments;
                    const context = this;
                    if (!inThrottle) {
                        func.apply(context, args);
                        inThrottle = true;
                        setTimeout(function() {
                            inThrottle = false;
                        }, limit);
                    }
                };
            }
        },
        
        /**
         * Gestione errori
         */
        ErrorHandler: {
            
            /**
             * Mostra messaggio di errore
             */
            showError: function(message, duration) {
                duration = duration || 5000;
                
                const $error = $(`
                    <div class="amacarun-notice amacarun-notice-error" style="position: fixed; top: 20px; right: 20px; z-index: 10000; max-width: 400px;">
                        <button type="button" class="notice-dismiss" style="float: right; background: none; border: none; font-size: 18px; cursor: pointer; padding: 0; margin: 0;">&times;</button>
                        <div>${message}</div>
                    </div>
                `);
                
                $('body').append($error);
                
                // Auto remove
                setTimeout(function() {
                    $error.fadeOut(300, function() {
                        $error.remove();
                    });
                }, duration);
                
                // Manual close
                $error.find('.notice-dismiss').on('click', function() {
                    $error.fadeOut(300, function() {
                        $error.remove();
                    });
                });
            },
            
            /**
             * Log errore per debug
             */
            logError: function(error, context) {
                if (console && console.error) {
                    console.error('AmacarUN Error:', error, context);
                }
            }
        },
        
        /**
         * Performance monitoring
         */
        Performance: {
            
            /**
             * Misura tempo di esecuzione
             */
            measureTime: function(name, func) {
                const start = performance.now();
                const result = func();
                const end = performance.now();
                
                console.log(`${name} took ${end - start} milliseconds`);
                return result;
            },
            
            /**
             * Ottimizza immagini per performance
             */
            optimizeImages: function() {
                $('img').each(function() {
                    const $img = $(this);
                    
                    // Add loading="lazy" se non presente
                    if (!$img.attr('loading')) {
                        $img.attr('loading', 'lazy');
                    }
                    
                    // Dimensioni responsive se non specificate
                    if (!$img.attr('sizes') && $img.attr('srcset')) {
                        $img.attr('sizes', '(max-width: 768px) 100vw, 50vw');
                    }
                });
            }
        },
        
        /**
         * Accessibilità
         */
        A11y: {
            
            /**
             * Migliora accessibilità tabelle
             */
            enhanceTableAccessibility: function() {
                $('.amacarun-participants-table').each(function() {
                    const $table = $(this);
                    
                    // Aggiungi scope agli headers
                    $table.find('thead th').attr('scope', 'col');
                    
                    // Aggiungi ruoli ARIA
                    $table.attr('role', 'table');
                    $table.find('thead').attr('role', 'rowgroup');
                    $table.find('tbody').attr('role', 'rowgroup');
                    $table.find('tr').attr('role', 'row');
                    $table.find('th, td').attr('role', 'cell');
                    
                    // Caption se mancante
                    if (!$table.find('caption').length) {
                        $table.prepend('<caption class="sr-only">Lista partecipanti evento</caption>');
                    }
                });
            },
            
            /**
             * Gestisce focus trap nei modals
             */
            trapFocus: function($modal) {
                const focusableElements = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                const firstFocusable = focusableElements.first();
                const lastFocusable = focusableElements.last();
                
                firstFocusable.focus();
                
                $modal.on('keydown', function(e) {
                    if (e.key === 'Tab') {
                        if (e.shiftKey) {
                            if (document.activeElement === firstFocusable[0]) {
                                lastFocusable.focus();
                                e.preventDefault();
                            }
                        } else {
                            if (document.activeElement === lastFocusable[0]) {
                                firstFocusable.focus();
                                e.preventDefault();
                            }
                        }
                    }
                    
                    if (e.key === 'Escape') {
                        $modal.removeClass('active');
                    }
                });
            },
            
            /**
             * Annunci per screen readers
             */
            announceToScreenReader: function(message) {
                const $announcement = $('<div>', {
                    'aria-live': 'polite',
                    'aria-atomic': 'true',
                    'class': 'sr-only',
                    text: message
                });
                
                $('body').append($announcement);
                
                setTimeout(function() {
                    $announcement.remove();
                }, 1000);
            }
        }
    };
    
    /**
     * Plugin per shortcode specifici
     */
    AmacarUNPublic.Shortcodes = {
        
        /**
         * Inizializza shortcode lista partecipanti
         */
        initParticipantsList: function() {
            $('.amacarun-participants-container').each(function() {
                const $container = $(this);
                
                // Auto-refresh se configurato
                const autoRefresh = $container.data('auto-refresh');
                if (autoRefresh) {
                    setInterval(function() {
                        AmacarUNPublic.Shortcodes.refreshParticipantsList($container);
                    }, autoRefresh * 1000);
                }
                
                // Inizializza ordinamento tabelle
                AmacarUNPublic.Shortcodes.initTableSorting($container);
            });
        },
        
        /**
         * Refresh lista partecipanti
         */
        refreshParticipantsList: function($container) {
            const currentUrl = window.location.href;
            
            $.get(currentUrl, function(data) {
                const $newContainer = $(data).find('.amacarun-participants-container').first();
                
                if ($newContainer.length) {
                    $container.html($newContainer.html());
                    AmacarUNPublic.animateCounters();
                }
            });
        },
        
        /**
         * Inizializza ordinamento tabelle
         */
        initTableSorting: function($container) {
            $container.find('.amacarun-participants-table th').each(function() {
                const $th = $(this);
                
                if (!$th.hasClass('no-sort')) {
                    $th.addClass('sortable').css('cursor', 'pointer');
                    
                    $th.on('click', function() {
                        AmacarUNPublic.Shortcodes.sortTable($th);
                    });
                }
            });
        },
        
        /**
         * Ordina tabella
         */
        sortTable: function($header) {
            const $table = $header.closest('table');
            const columnIndex = $header.index();
            const isAscending = !$header.hasClass('sort-desc');
            
            // Remove previous sort classes
            $table.find('th').removeClass('sort-asc sort-desc');
            
            // Add new sort class
            $header.addClass(isAscending ? 'sort-asc' : 'sort-desc');
            
            // Get all rows except header
            const $rows = $table.find('tbody tr').get();
            
            // Sort rows
            $rows.sort(function(a, b) {
                const aValue = $(a).find('td').eq(columnIndex).text().trim();
                const bValue = $(b).find('td').eq(columnIndex).text().trim();
                
                // Check if values are numbers
                const aNum = parseFloat(aValue);
                const bNum = parseFloat(bValue);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAscending ? aNum - bNum : bNum - aNum;
                } else {
                    return isAscending ? 
                        aValue.localeCompare(bValue) : 
                        bValue.localeCompare(aValue);
                }
            });
            
            // Reorder rows in DOM
            $.each($rows, function(index, row) {
                $table.find('tbody').append(row);
            });
        }
    };
    
    // Inizializzazione quando DOM è pronto
    $(document).ready(function() {
        AmacarUNPublic.init();
        AmacarUNPublic.Shortcodes.initParticipantsList();
        AmacarUNPublic.A11y.enhanceTableAccessibility();
        AmacarUNPublic.Performance.optimizeImages();
    });
    
    // Inizializzazione dopo caricamento completo
    $(window).on('load', function() {
        AmacarUNPublic.animateCounters();
        
        // Trigger scroll animations
        $(window).trigger('scroll');
    });
    
    // Gestione resize window
    $(window).on('resize', AmacarUNPublic.Utils.throttle(function() {
        AmacarUNPublic.makeTablesResponsive();
    }, 250));
    
    // Esporta per uso globale
    window.AmacarUNPublic = AmacarUNPublic;
    
})(jQuery);