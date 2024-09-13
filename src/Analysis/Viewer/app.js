/*
 * This file is part of vsr extend analysis
 * @author Vitor Reis <vitor@d5w.com.br>
 */

import * as Vue from 'vue';
import * as VueSfc from 'vue3-sfc-loader';
import * as Bootstrap from 'bootstrap';
import * as ChartJS from 'chart.js';
import * as VueChart from "vue-chartjs";
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-bs5';

window.analysis = {
    href: window.location.href,
    geturl(action, params = {}) {
        const url = new URL(this.href);
        url.searchParams.set('d5whub-extend-analysis', action);
        for (const [key, value] of Object.entries(params)) url.searchParams.set(key, value);
        return url.href;
    },
    async action(action, params = {}, data = {}, json = true) {
        return await fetch(this.geturl(action, params), {
            method: data ? 'POST' : 'GET',
            body: data ? JSON.stringify(data) : null
        }).then(response => json ? response.json() : response.text());
    },

    component(file) {
        return Vue.defineAsyncComponent(async () => await VueSfc.loadModule(file + ".vue", {
            moduleCache: {  vue: Vue },
            devMode: true,
            getFile: async () => await this.action('file', {file}, false, false),
            addStyle(textContent) {
                const style = Object.assign(document.createElement('style'), {textContent});
                const ref = document.head.getElementsByTagName('style')[0] || null;
                document.head.insertBefore(style, ref);
            },
        }));
    },

    settings: {
        autoUpdateInterval: 1500,
        chart: {
            groupBy: 's10',
            length: 100
        },
        tolerance: {
            server: {
                cpu: {
                    danger: 80,
                    warning: 60
                },
                disk: {
                    danger: 80,
                    warning: 60
                },
                memory: {
                    danger: 80,
                    warning: 60
                },
                db_size: {
                    danger: 10 * 1024,
                    warning: 5 * 1024
                }
            },
            request: {
                duration: {
                    danger: 1000,
                    warning: 500
                },
                memory: {
                    danger: 30,
                    warning: 10
                },
                http_code: {
                    danger: 500,
                    warning: 400
                },
                profile_count: {
                    danger: 1000,
                    warning: 500
                },
            }
        },
        ...JSON.parse(localStorage.getItem('analysis.settings') || "{}")
    },
    saveSettings() {
        localStorage.setItem('analysis.settings', JSON.stringify(analysis.settings));
    },

    format: {
        number(value, decimals = 2) {
            if (value === undefined || value === null) value = 0;
            return parseFloat(value)
                .toFixed(decimals)
                .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1');
        },
        percent(value) {
            if (!value || value <= 0) return '-';
            return analysis.format.number(value, 1) + '%';
        },
        size(value, suffix = '') {
            return analysis.format.number(value, 1) + suffix;
        },
        second(value) {
            return analysis.format.number(value, 3) + 's';
        },
        date(format, timestamp = 0) {
            let date = new Date(timestamp * 1000);
            let d = date.getDate().toString().padStart(2, '0');
            let m = (date.getMonth() + 1).toString().padStart(2, '0');
            let Y = date.getFullYear().toString().padStart(4, '0');
            let H = date.getHours().toString().padStart(2, '0');
            let i = date.getMinutes().toString().padStart(2, '0');
            let s = date.getSeconds().toString().padStart(2, '0');

            return format
                .replace('Y', Y)
                .replace('m', m)
                .replace('d', d)
                .replace('H', H)
                .replace('i', i)
                .replace('s', s);
        },
        label(value, groupBy) {
            switch (groupBy) {
                case 's10':
                    return analysis.format.date(`H:i:s`, value);
                case 'i':
                case 'i10':
                    return analysis.format.date(`H:i`, value);
                case 'H':
                    return analysis.format.date(`H`, value);
                case 'd':
                    return analysis.format.date(`d/m`, value);
                default:
                    return analysis.format.date(`d/m/Y`, value);
            }
        },
        tolerance(colors, value) {
            let result = {bg: 'secondary-subtle', text: 'black'};
            for (let i in colors) {
                if (i !== 'default' && value >= colors[i]) {
                    result = i === 'danger'
                        ? {bg: 'danger', text: 'white'}
                        : {bg: 'warning', text: 'black'};
                    break;
                }
            }
            return result;
        },
    }
};

let url = new URL(window.location.href), main;
if (url.searchParams.get('d5whub-extend-analysis') === 'request') {
    main = analysis.component('/page/Request.vue');
} else {
    main = analysis.component('/page/Dashboard.vue');
}

ChartJS.Chart.register(...ChartJS.registerables);
DataTable.use(DataTablesCore);

window.bootstrap = Bootstrap;
window.ChartJS = VueChart;

const app = Vue.createApp(main);
app.config.globalProperties.analysis = window.analysis = Vue.reactive(window.analysis);
app.config.devtools = true;
app.config.performance = true;
app.use(DataTable);

document.body.id = 'app';
app.mount('#app');
