import { Controller } from '@hotwired/stimulus';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';

const frLocale = {
    code: 'fr',
    week: { dow: 1, doy: 4 },
    buttonText: { prev: 'Précédent', next: 'Suivant', today: "Aujourd'hui", month: 'Mois', week: 'Semaine', day: 'Jour', list: 'Liste' },
    weekText: 'Sem.',
    allDayText: 'Toute la journée',
    noEventsText: 'Aucune transaction ce mois',
};

const TYPE_LABELS = { DEPOT: 'Dépôt', RETRAIT: 'Retrait', VIREMENT: 'Virement', PAIEMENT: 'Paiement' };
const TYPE_COLORS = { DEPOT: '#22c55e', RETRAIT: '#f59e0b', VIREMENT: '#3b82f6', PAIEMENT: '#ef4444' };
const TYPE_ICONS  = { DEPOT: '↓', RETRAIT: '↑', VIREMENT: '⇄', PAIEMENT: '💳' };

export default class extends Controller {
    static values = { events: Array, routeBase: String }

    connect() {
        console.log('FullCalendar controller connected');
        console.log('Events value:', this.eventsValue);
        console.log('Route base:', this.routeBaseValue);
        
        if (!this.eventsValue || this.eventsValue.length === 0) {
            console.warn('No events to display in calendar');
        }
        
        try {
            this.calendar = new Calendar(this.element, {
                plugins: [dayGridPlugin, listPlugin, interactionPlugin],
                locale: frLocale,
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek,listWeek',
                },
                events: this.eventsValue.map(t => ({
                    id: String(t.id),
                    title: `${TYPE_ICONS[t.type] || ''} ${TYPE_LABELS[t.type] || t.type}  ${parseFloat(t.amount).toFixed(2)} DT`,
                    start: t.start,
                    backgroundColor: TYPE_COLORS[t.type] || '#6366f1',
                    borderColor: 'transparent',
                    textColor: '#fff',
                    extendedProps: t,
                })),
                eventClick: (info) => {
                    const t = info.event.extendedProps;
                    window.location.href = this.routeBaseValue + '?edit_id=' + t.id + '#tnx-form-anchor';
                },
                eventMouseEnter: (info) => this.#showTooltip(info.el, info.event.extendedProps),
                eventMouseLeave: () => this.#hideTooltip(),
                height: 'auto',
                aspectRatio: 1.8,
                eventDisplay: 'block',
                displayEventTime: false,
                dayMaxEvents: 3,
                moreLinkText: (n) => `+ ${n} autres`,
            });

            console.log('Calendar instance created, rendering...');
            this.calendar.render();
            console.log('Calendar rendered successfully');
            this.element._stimulus_fullcalendar = this;
        } catch (error) {
            console.error('Error creating/rendering calendar:', error);
        }
    }

    disconnect() {
        this.calendar?.destroy();
        this.#hideTooltip();
        delete this.element._stimulus_fullcalendar;
    }

    #showTooltip(el, t) {
        this.#hideTooltip();
        const color = TYPE_COLORS[t.type] || '#6366f1';
        const tip = document.createElement('div');
        tip.className = 'calendar-tooltip';
        tip.innerHTML = `
            <div class="calendar-tooltip-title" style="color:${color}">
                ${TYPE_ICONS[t.type] || '•'} ${TYPE_LABELS[t.type] || t.type}
            </div>
            <div class="calendar-tooltip-amount">${parseFloat(t.amount).toFixed(2)} DT</div>
            ${t.category ? `<div><i class="fa-solid fa-tag"></i> ${t.category}</div>` : ''}
            ${t.description ? `<div style="opacity:.8;font-size:.8rem">${t.description}</div>` : ''}
            <div class="calendar-tooltip-meta">👤 ${t.userName}</div>
        `;
        document.body.appendChild(tip);
        const rect = el.getBoundingClientRect();
        tip.style.cssText = `position:fixed;left:${rect.left + rect.width / 2 - tip.offsetWidth / 2}px;top:${rect.top - tip.offsetHeight - 10}px;z-index:9999`;
        this._tooltip = tip;
    }

    #hideTooltip() {
        this._tooltip?.remove();
        this._tooltip = null;
    }
}
