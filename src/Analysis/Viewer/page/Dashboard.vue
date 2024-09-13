<!--
  - This file is part of vsr extend analysis
  - @author Vitor Reis <vitor@d5w.com.br>
  -->

<template>
  <div class="container-fluid vh-100 w-100 d-flex flex-column align-content-stretch">
    <div class="position-fixed d-flex justify-content-between align-items-center mt-2 me-3"
         style="right:0;top:0;width:115px">
      <span><span :class="'text-'+reportStatus.class">⬤</span> {{ reportStatus.text }}</span>
      <div>
        <button type="button" :class="'btn border btn-sm me-2 ' + (wheres.length > 0 ? 'btn-primary' : 'btn-light')"
                data-bs-toggle="modal" data-bs-target="#search">
          <i :class="'fa fa-search ' + (wheres.length > 0 ? 'text-white' : '')"></i>
        </button>
        <button type="button" class="btn btn-light border btn-sm" data-bs-toggle="modal" data-bs-target="#settings">
          <i class="fa fa-cog"></i>
        </button>
      </div>
    </div>
    <div class="row">
      <div class="col-md-4 col-12">
        <div class="row">
          <div id="current-charts" class="col-md-2 col-12 p-1 pb-2">
            <DoughnutChat id="chart-cpu" :data="server.cpu.chart.data" :options="server.cpu.chart.options"/>
            <DoughnutChat id="chart-disk" :data="server.disk.chart.data" :options="server.disk.chart.options"/>
            <DoughnutChat id="chart-memory" :data="server.memory.chart.data" :options="server.memory.chart.options"/>
          </div>
          <div id="current"
               class="col-md-10 col-12 h-100 d-flex flex-column align-items-start justify-content-between py-2">
            <span class="badge text-dark-emphasis">Up: {{ server.uptime }}</span>
            <span class="badge text-dark-emphasis">
              CPU: <span :class="'text-'+server.cpu.color.bg">{{ server.cpu.percent }}</span>
            </span>
            <span class="badge text-dark-emphasis">
              Disk: <span :class="'text-'+server.disk.color.bg">{{ server.disk.percent }}</span>
              ({{ server.disk.used }}/{{ server.disk.total }}, {{ server.disk.free }} free)
            </span>
            <span class="badge text-dark-emphasis">
              Memory: <span :class="'text-'+server.memory.color.bg">{{ server.memory.percent }}</span>
              ({{ server.memory.used }}/{{ server.memory.total }}, {{ server.memory.free }} free,
              {{ server.memory.cache }} cache)
            </span>
            <span class="badge text-dark-emphasis">
              Swap: {{ server.swap.used }}/{{ server.swap.total }}, {{ server.swap.free }} free,
              {{ server.swap.cache }} cache
            </span>
            <span class="badge text-dark-emphasis">
              Tasks: {{ server.thread.running }} running, {{ server.thread.total }} total,
              {{ server.thread.sleeping }} sleeping, {{ server.thread.stopped }} stopped, {{ server.thread.zombie }} zombie
            </span>
            <span class="badge text-dark-emphasis">
              Requests: <span :class="'text-'+request.color.bg">{{ request.avg }}</span>,
              hits: {{ request.total }} ({{ request.per_second }}/s, {{ request.per_minute }}/m,
              {{ request.per_hour }}/h, {{ request.per_day }}/d)
            </span>
            <span class="badge text-danger-emphasis">
              DB Size: <span :class="'text-'+server?.db_size_color.bg">{{ server?.db_size }}</span>
            </span>
          </div>
        </div>
      </div>
      <div class="col-md-4 py-2">
        <LineChat id="chart-server" :data="server.chart.data" :options="server.chart.options"/>
      </div>
      <div class="col-md-4 py-2">
        <LineChat id="chart-request" :data="request.chart.data" :options="request.chart.options"/>
      </div>
    </div>
    <div class="row" id="dts">
      <div class="col-md-4 my-2" @click="request.dt.keys.click">
        <DatatableEx id="dt-keys" ref="dt-keys" :columns="request.dt.keys.columns" :options="request.dt.keys.options"/>
      </div>
      <div class="col-md-8 my-2" @click="request.dt.reqs.click">
        <DatatableEx id="dt-reqs" ref="dt-reqs" :columns="request.dt.reqs.columns" :options="request.dt.reqs.options"/>
      </div>
    </div>
  </div>

  <div class="modal fade" id="settings" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-cog"></i> Settings</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <h5>Auto-update settings</h5>
          <div class="row mb-1">
            <label for="input-autoUpdateInterval" class="col-form-label col-sm-4">Interval</label>
            <div class="col-sm-8">
              <div class="input-group">
                <input type="number" min="0" id="input-autoUpdateInterval" class="form-control"
                       v-model="analysis.settings.autoUpdateInterval"/>
                <span class="input-group-text">ms</span>
              </div>
            </div>
          </div>

          <h5 class="mt-3">Chart settings</h5>
          <div class="row mb-1">
            <label for="input-chartGroupBy" class="col-form-label col-sm-4">Group by</label>
            <div class="col-sm-8">
              <select id="input-chartGroupBy" class="form-select" v-model="analysis.settings.chart.groupBy"
                      @change="updateData">
                <option value="s10">10 Seconds</option>
                <option value="i">Minutes</option>
                <option value="i10">10 Minutes</option>
                <option value="h">Hours</option>
                <option value="d">Days</option>
              </select>
            </div>
          </div>
          <div class="row mb-1">
            <label for="input-chartLength" class="col-form-label col-sm-4">Length</label>
            <div class="col-sm-8">
              <input type="number" min="10" max="1000" id="input-chartLength" class="form-control"
                     v-model="analysis.settings.chart.length" @change="updateData"/>
            </div>
          </div>

          <h5 class="mt-3">Tolerance settings</h5>
          <div class="row mb-1">
            <label for="input-tolerance-server-cpu" class="col-form-label col-sm-4">CPU</label>
            <div class="col-sm-8">
              <div class="row g-3">
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-warning">Warning</span>
                    <input type="number" min="0" max="100" id="input-tolerance-server-cpu-warning" class="form-control"
                           v-model="analysis.settings.tolerance.server.cpu.warning"/>
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-danger text-white">Danger</span>
                    <input type="number" min="0" max="100" id="input-tolerance-server-cpu-danger" class="form-control"
                           v-model="analysis.settings.tolerance.server.cpu.danger"/>
                    <span class="input-group-text">%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row mb-1">
            <label for="input-tolerance-server-disk" class="col-form-label col-sm-4">Disk</label>
            <div class="col-sm-8">
              <div class="row g-3">
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-warning">Warning</span>
                    <input type="number" min="0" max="100" id="input-tolerance-server-disk-warning" class="form-control"
                           v-model="analysis.settings.tolerance.server.disk.warning"/>
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-danger text-white">Danger</span>
                    <input type="number" min="0" max="100" id="input-tolerance-server-disk-danger" class="form-control"
                           v-model="analysis.settings.tolerance.server.disk.danger"/>
                    <span class="input-group-text">%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row mb-1">
            <label for="input-tolerance-server-memory" class="col-form-label col-sm-4">Memory</label>
            <div class="col-sm-8">
              <div class="row g-3">
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-warning">Warning</span>
                    <input type="number" min="0" max="100" id="input-tolerance-server-memory-warning"
                           class="form-control" v-model="analysis.settings.tolerance.server.memory.warning"/>
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-danger text-white">Danger</span>
                    <input type="number" min="0" max="100" id="input-tolerance-server-memory-danger"
                           class="form-control" v-model="analysis.settings.tolerance.server.memory.danger"/>
                    <span class="input-group-text">%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row mb-1">
            <label for="input-tolerance-server-db_size" class="col-form-label col-sm-4">DB Size</label>
            <div class="col-sm-8">
              <div class="row g-3">
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-warning">Warning</span>
                    <input type="number" min="0" id="input-tolerance-server-db_size-warning" class="form-control"
                           v-model="analysis.settings.tolerance.server.db_size.warning"/>
                    <span class="input-group-text">MB</span>
                  </div>
                </div>
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-danger text-white">Danger</span>
                    <input type="number" min="0" id="input-tolerance-server-db_size-danger" class="form-control"
                           v-model="analysis.settings.tolerance.server.db_size.danger"/>
                    <span class="input-group-text">MB</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row mb-1">
            <label for="input-tolerance-request-duration" class="col-form-label col-sm-4">Request Duration</label>
            <div class="col-sm-8">
              <div class="row g-3">
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-warning">Warning</span>
                    <input type="number" min="0" id="input-tolerance-request-duration-warning" class="form-control"
                           v-model="analysis.settings.tolerance.request.duration.warning"/>
                    <span class="input-group-text">ms</span>
                  </div>
                </div>
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-danger text-white">Danger</span>
                    <input type="number" min="0" id="input-tolerance-request-duration-danger" class="form-control"
                           v-model="analysis.settings.tolerance.request.duration.danger"/>
                    <span class="input-group-text">ms</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row mb-1">
            <label for="input-tolerance-request-memory" class="col-form-label col-sm-4">Request Memory</label>
            <div class="col-sm-8">
              <div class="row g-3">
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-warning">Warning</span>
                    <input type="number" min="0" id="input-tolerance-request-memory-warning" class="form-control"
                           v-model="analysis.settings.tolerance.request.memory.warning"/>
                    <span class="input-group-text">MB</span>
                  </div>
                </div>
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-danger text-white">Danger</span>
                    <input type="number" min="0" id="input-tolerance-request-memory-danger" class="form-control"
                           v-model="analysis.settings.tolerance.request.memory.danger"/>
                    <span class="input-group-text">MB</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row mb-1">
            <label for="input-tolerance-request-profile_count" class="col-form-label col-sm-4">Request Profile
              Count</label>
            <div class="col-sm-8">
              <div class="row g-3">
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-warning">Warning</span>
                    <input type="number" min="0" id="input-tolerance-request-profile_count-warning" class="form-control"
                           v-model="analysis.settings.tolerance.request.profile_count.warning"/>
                  </div>
                </div>
                <div class="col">
                  <div class="input-group">
                    <span class="input-group-text bg-danger text-white">Danger</span>
                    <input type="number" min="0" id="input-tolerance-request-profile_count-danger" class="form-control"
                           v-model="analysis.settings.tolerance.request.profile_count.danger"/>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <h5 class="mt-3">Ignore request keys</h5>
          <div class="row mb-1">
            <div class="col-sm-12">
              <textarea id="input-ignore-request-keys" class="form-control" rows="4"
                        placeholder="One request key per line and use * as wildcard"></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="search" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-search"></i> Search</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <h5>Add condition</h5>
          <div class="row mb-4">
            <div class="input-group">
              <select class="form-select" v-model="addWhere.field" style="width:20%"
                      @change="addWhere.operator='=';addWhere.value=''">
                <optgroup label="all">
                  <option value="request_key">request_key</option>
                </optgroup>
                <optgroup label="only dt.hits">
                  <option value="hits">hits</option>
                  <option value="avg">avg</option>
                  <option value="min">min</option>
                  <option value="max">max</option>
                  <option value="last">last</option>
                </optgroup>
                <optgroup label="only dt.reqs">
                  <option value="date">date</option>
                  <option value="duration">duration</option>
                  <option value="memory">memory</option>
                  <option value="profile_count">profile_count</option>
                  <option value="http_code">http_code</option>
                  <option value="method">method</option>
                  <option value="url">url</option>
                  <option value="error">error</option>
                  <option value="extra">extra</option>
                </optgroup>
              </select>
              <select class="form-select" style="width:20%" v-model="addWhere.operator">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">"
                        v-if="['request_key','method','url','extra'].indexOf(addWhere.field) === -1">>
                </option>
                <option value=">="
                        v-if="['request_key','method','url','extra'].indexOf(addWhere.field) === -1">>=
                </option>
                <option value="<"
                        v-if="['request_key','method','url','extra'].indexOf(addWhere.field) === -1"><
                </option>
                <option value="<="
                        v-if="['request_key','method','url','extra'].indexOf(addWhere.field) === -1"><=
                </option>
                <option value="LIKE">LIKE</option>
                <option value="NOT LIKE">NOT LIKE</option>
              </select>
              <input type="datetime-local" class="form-control" v-model="addWhere.value" style="width:40%"
                     placeholder="NULL" v-if="['date'].indexOf(addWhere.field) !== -1">
              <input type="number" step="0.1" class="form-control" v-model="addWhere.value" style="width:40%"
                     placeholder="NULL"
                     v-else-if="['duration','memory','avg','min','max','last'].indexOf(addWhere.field) !== -1">
              <input type="number" step="1" class="form-control" v-model="addWhere.value" style="width:40%"
                     placeholder="NULL" v-else-if="['profile_count','http_code','hits','error'].indexOf(addWhere.field) !== -1">
              <select class="form-select" v-model="addWhere.value" style="width:40%"
                      v-else-if="['method'].indexOf(addWhere.field) !== -1">
                <option value="GET">GET</option>
                <option value="POST">POST</option>
                <option value="PUT">PUT</option>
                <option value="DELETE">DELETE</option>
                <option value="PATCH">PATCH</option>
                <option value="CONNECT">CONNECT</option>
                <option value="OPTIONS">OPTIONS</option>
                <option value="TRACE">TRACE</option>
              </select>
              <select class="form-select" v-model="addWhere.value" style="width:40%"
                      v-else-if="['has_error'].indexOf(addWhere.field) !== -1">
                <option value="TRUE">TRUE</option>
                <option value="FALSE">FALSE</option>
              </select>
              <input type="text" class="form-control" v-model="addWhere.value" style="width:40%" placeholder="NULL"
                     v-else>

              <button class="btn btn-light border" type="button"
                      @click="addWhere.value = addWhere.value ? addWhere.value : 'NULL'; wheres.push({ ...addWhere }); updateData()">
                <i class="fa fa-plus"></i>
              </button>
            </div>
          </div>

          <h5>Current conditions</h5>
          <div class="row" v-if="wheres.length < 1">
            <div class="col text-center text-center">
              No conditions!
            </div>
          </div>
          <table class="table table-sm align-middle m-0" v-else>
            <tbody>
            <tr v-for="where in wheres">
              <td>
                `{{ where.field }}`
                {{ where.operator }}
                <span
                    v-html="where.value ? (/^-?\d+(\.\d+)?$/.test(where.value) ? where.value : (['NULL', 'FALSE', 'TRUE'].indexOf(where.value?.toUpperCase()) !== -1 ? `<i>${where.value?.toUpperCase()}</i>` : `&quot;${where.value}&quot;`)) : '<i>NULL</i>'"/>
              </td>
              <td class="text-end">
                <select class="form-select form-select-sm d-inline-block me-2" v-model="where.and_or" style="width:auto">
                  <option value="AND">AND</option>
                  <option value="OR">OR</option>
                </select>
                <button class="btn btn-danger btn-sm" type="button" @click="wheres.splice(wheres.indexOf(where), 1)">
                  <i class="fa fa-trash text-white"></i>
                </button>
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const {Doughnut: DoughnutChat, Line: LineChat} = window.ChartJS;
const DatatableEx = analysis.component('components/DatatableEx.vue');

export function dtPageLength() {
  if (window.innerWidth < 768) return 10;
  let height = window.innerHeight - 325;
  let pageLength = Math.floor(height / 25);
  if (pageLength < 10) pageLength = 10;
  return pageLength;
}

export default {
  name: "Dashboard",
  components: {
    DoughnutChat,
    LineChat,
    DatatableEx
  },
  methods: {
    resize() {
      let new_len = dtPageLength();

      let old_len_reqs = this?.$refs['dt-reqs']?.$refs.dt.dt?.page.info()?.length;
      if (old_len_reqs && old_len_reqs !== new_len) {
        this?.$refs['dt-reqs']?.$refs.dt.dt?.page.len(new_len);
        this?.$refs['dt-reqs']?.$refs.dt.dt?.draw(false);
      }

      let old_len_keys = this?.$refs['dt-keys']?.$refs.dt.dt?.page.info()?.length;
      if (old_len_keys && old_len_keys !== new_len) {
        this?.$refs['dt-keys']?.$refs.dt.dt?.page.len(new_len);
        this?.$refs['dt-keys']?.$refs.dt.dt?.draw(false);
      }

      if (old_len_reqs !== new_len || old_len_keys !== new_len) {
        this.updateData();
      }
    },
    async updateData() {
      if (this.updateDataTimeout) {
        clearTimeout(this.updateDataTimeout);
        this.updateDataTimeout = null;
      }

      if (typeof this?.$refs['dt-reqs'] === 'undefined' || typeof this?.$refs['dt-keys'] === 'undefined') {
        this.updateDataTimeout = setTimeout(() => this.updateData(), 10);
        return;
      }

      this.reportStatus.class = 'warning';
      this.reportStatus.text = '...';

      let dtReqsOrder = this?.$refs['dt-reqs']?.$refs.dt.dt?.order() || false;
      dtReqsOrder = dtReqsOrder[0] || false;
      dtReqsOrder = dtReqsOrder[0] || false;
      if (dtReqsOrder) {
        dtReqsOrder = this?.$refs['dt-reqs']?.$refs.dt.dt?.settings()[0].aoColumns[dtReqsOrder].data;
      } else {
        dtReqsOrder = 'start';
      }

      let dtReqsDir = this.$refs['dt-reqs']?.$refs.dt.dt?.order() || false;
      dtReqsDir = dtReqsDir[0] || false;
      dtReqsDir = dtReqsDir[1] || 'desc';

      let dtKeysOrder = this?.$refs['dt-keys']?.$refs.dt.dt?.order() || false;
      dtKeysOrder = dtKeysOrder[0] || false;
      dtKeysOrder = dtKeysOrder[0] || false;
      if (dtKeysOrder) {
        dtKeysOrder = this?.$refs['dt-keys']?.$refs.dt.dt?.settings()[0].aoColumns[dtKeysOrder].data;
      } else {
        dtKeysOrder = 'avg';
      }

      let dtKeysDir = this?.$refs['dt-keys']?.$refs.dt.dt?.order() || false;
      dtKeysDir = dtKeysDir[0] || false;
      dtKeysDir = dtKeysDir[1] || 'desc';

      let start_time = new Date().getTime();
      this.report = await analysis.action('viewer-data', {
        chartGroupBy: analysis.settings.chart.groupBy,
        chartLength: analysis.settings.chart.length,
        dtReqsStart: this?.$refs['dt-reqs']?.$refs.dt.dt?.page.info()?.start || 0,
        dtReqsLength: this?.$refs['dt-reqs']?.$refs.dt.dt?.page.info()?.length || 10,
        dtReqsOrder,
        dtReqsDir,
        dtKeysStart: this?.$refs['dt-keys']?.$refs.dt.dt?.page.info()?.start || 0,
        dtKeysLength: this?.$refs['dt-keys']?.$refs.dt.dt?.page.info()?.length || 10,
        dtKeysOrder,
        dtKeysDir,
        wheres: JSON.stringify([...this.wheres.map(i => [i.field, i.operator, i.value, i.and_or])]),
      });

      this?.$refs['dt-keys']?.redraw(
          this.report.request?.dt.keys.data,
          this.report.request?.dt.keys.recordsTotal,
          this.report.request?.dt.keys.recordsFiltered
      );
      this?.$refs['dt-reqs']?.redraw(
          this.report.request?.dt.reqs.data,
          this.report.request?.dt.reqs.recordsTotal,
          this.report.request?.dt.reqs.recordsFiltered
      );

      let end_time = new Date().getTime();

      this.reportStatus.class = 'success';
      this.reportStatus.text = ` ${analysis.format.second((end_time - start_time) / 1000)}`;

      this.updateDataTimeout = setTimeout(() => this.updateData(), analysis.settings.autoUpdateInterval);
    }
  },
  data() {
    return {
      updateDataTimeout: null,
      addWhere: {
        field: 'request_key',
        operator: '=',
        value: null,
        and_or: 'AND'
      },
      wheres: [],
      report: {},
      reportStatus: {
        text: '...',
        class: 'warning'
      }
    }
  },

  computed: {
    server() {
      let uptime = this.report.server?.current?.uptime || 0; // in minutes
      let uptime_days = Math.floor(uptime / 1440);
      let uptime_hours = Math.floor((uptime - (uptime_days * 1440)) / 60);
      let uptime_minutes = Math.floor(uptime - (uptime_days * 1440) - (uptime_hours * 60));

      uptime = '';
      if (uptime_days > 0) uptime += ` ${uptime_days}d`;
      if (uptime_hours > 0) uptime += ` ${uptime_hours}h`;
      if (uptime_minutes > 0) uptime += ` ${uptime_minutes}m`;
      if (uptime === '') uptime = '-';

      let cpu_percent = this.report.server?.current?.cpu || 0;
      let disk_percent = (this.report.server?.current?.disk_used * 100 / this.report.server?.current?.disk_total) || 0;
      let memory_percent = (this.report.server?.current?.mem_used * 100 / this.report.server?.current?.mem_total) || 0;

      return {
        uptime,
        cpu: {
          percent: analysis.format.percent(cpu_percent),
          color: analysis.format.tolerance(analysis.settings.tolerance.server.cpu, cpu_percent),
          chart: {
            data: {
              labels: ['Free', 'Usage'],
              datasets: [
                {
                  data: [analysis.format.number(100 - parseFloat(cpu_percent), 1), analysis.format.number(cpu_percent, 1)],
                  backgroundColor: ['rgb(25,135,84)', 'rgba(220,53,69,1)']
                }
              ]
            },
            options: {
              responsive: true,
              plugins: {
                legend: {display: false},
                title: {display: true, text: 'CPU', padding: 5, font: {size: 12}}
              }
            }
          }
        },
        disk: {
          percent: analysis.format.percent(disk_percent),
          color: analysis.format.tolerance(analysis.settings.tolerance.server.disk, disk_percent),
          free: analysis.format.size(this.report.server?.current.disk_free),
          used: analysis.format.size(this.report.server?.current.disk_used),
          total: analysis.format.size(this.report.server?.current.disk_total, ' GB'),
          chart: {
            data: {
              labels: ['Free', 'Usage'],
              datasets: [
                {
                  data: [analysis.format.number(100 - disk_percent, 1), analysis.format.number(disk_percent, 1)],
                  backgroundColor: ['rgb(25,135,84)', 'rgba(220,53,69,1)']
                }
              ]
            },
            options: {
              responsive: true,
              plugins: {
                legend: {display: false},
                title: {display: true, text: 'Disk', padding: 5, font: {size: 12}}
              }
            }
          }
        },
        memory: {
          percent: analysis.format.percent(memory_percent),
          color: analysis.format.tolerance(analysis.settings.tolerance.server.memory, memory_percent),
          free: analysis.format.size(this.report.server?.current.mem_free),
          used: analysis.format.size(this.report.server?.current.mem_used),
          total: analysis.format.size(this.report.server?.current.mem_total, ' MB'),
          cache: analysis.format.size(this.report.server?.current.mem_cache),
          chart: {
            data: {
              labels: ['Free', 'Usage'],
              datasets: [
                {
                  data: [analysis.format.number(100 - memory_percent, 1), analysis.format.number(memory_percent, 1)],
                  backgroundColor: ['rgb(25,135,84)', 'rgba(220,53,69,1)']
                }
              ]
            },
            options: {
              responsive: true,
              plugins: {
                legend: {display: false},
                title: {display: true, text: 'Memory', padding: 5, font: {size: 12}}
              }
            }
          }
        },
        swap: {
          percent: analysis.format.percent(this.report.server?.current.swa_used * 100 / this.report.server?.current.swa_total),
          free: analysis.format.size(this.report.server?.current.swa_free),
          used: analysis.format.size(this.report.server?.current.swa_used),
          total: analysis.format.size(this.report.server?.current.swa_total, ' MB'),
          cache: analysis.format.size(this.report.server?.current.swa_cache)
        },
        thread: {
          total: this.report.server?.current.thr_total,
          running: this.report.server?.current.thr_running,
          sleeping: this.report.server?.current.thr_sleeping,
          stopped: this.report.server?.current.thr_stopped,
          zombie: this.report.server?.current.thr_zombie
        },

        db_size: `${analysis.format.size(this.report.server?.current?.db_size?.full || 0, ' MB')} (${analysis.format.size(this.report.server?.current?.db_size?.file || 0, ' MB')})`,
        db_size_color: analysis.format.tolerance(analysis.settings.tolerance.server.db_size?.full, this.report.server?.current?.db_size?.full || 0),

        chart: {
          data: {
            labels: Object.keys(this.report.server?.chart || {}).map(i => analysis.format.label(i, analysis.settings.chart.groupBy)),
            datasets: [{
              label: 'CPU',
              data: Object.values(this.report.server?.chart || {}).map(i => analysis.format.number(i.cpu, 1)),
              borderWidth: 1,
              pointRadius: 1,
              backgroundColor: 'rgb(220,53,69)',
              borderColor: 'rgb(220,53,69)'
            }, {
              label: 'Memory',
              data: Object.values(this.report.server?.chart || {}).map(i => analysis.format.number(i.mem_used * 100 / this.report.server?.current.mem_total, 1)),
              borderWidth: 1,
              pointRadius: 1,
              backgroundColor: 'rgb(13,110,253)',
              borderColor: 'rgb(13,110,253)'
            }, {
              label: 'Disk',
              data: Object.values(this.report.server?.chart || {}).map(i => analysis.format.number(i.disk_used * 100 / this.report.server?.current.disk_total, 1)),
              borderWidth: 1,
              pointRadius: 1,
              backgroundColor: 'rgb(108,117,125)',
              borderColor: 'rgb(108,117,125)'
            }]
          },
          options: {
            responsive: true,
            animation: false,
            scales: {
              x: {ticks: {autoSize: false, maxRotation: 90, minRotation: 90, font: {size: 11}}},
              y: {beginAtZero: true, min: 0, max: 100, font: {size: 11}}
            }
          }
        }
      }
    },
    request() {
      return {
        avg: analysis.format.second(this.report.request?.current.avg),
        color: analysis.format.tolerance(analysis.settings.tolerance.request.duration, this.report.request?.current.avg * 1000),
        total: analysis.format.number(this.report.request?.current.total, 0),
        per_second: analysis.format.number(this.report.request?.current.per.second, 0),
        per_minute: analysis.format.number(this.report.request?.current.per.minute, 0),
        per_hour: analysis.format.number(this.report.request?.current.per.hour, 0),
        per_day: analysis.format.number(this.report.request?.current.per.day, 0),

        chart: {
          data: {
            labels: Object.keys(this.report.request?.chart || {}).map(i => analysis.format.label(i, analysis.settings.chart.groupBy)),
            datasets: [{
              label: 'Requests',
              data: Object.values(this.report.request?.chart || {}).map(i => analysis.format.number(i, 0)),
              borderWidth: 1,
              pointRadius: 1,
              backgroundColor: 'rgb(13,110,253)',
              borderColor: 'rgb(13,110,253)'
            }]
          },
          options: {
            responsive: true,
            animation: false,
            scales: {
              x: {ticks: {autoSize: false, maxRotation: 90, minRotation: 90, font: {size: 11}}},
              y: {beginAtZero: true, font: {size: 11}}
            }
          }
        },
        dt: {
          reqs: {
            columns: [
              {
                data: "start",
                title: "Date",
                className: 'text-center',
                render: (data) => {
                  return analysis.format.date('Y-m-d H:i:s', data);
                }
              },
              {
                data: "duration",
                title: "Duration",
                className: 'text-center',
                render: (data) => {
                  let color = analysis.format.tolerance(analysis.settings.tolerance.request.duration, data * 1000);
                  return `<span class="badge bg-${color.bg} text-${color.text}">${analysis.format.second(data)}</span>`;
                }
              },
              {
                data: "memory",
                title: "Memory",
                className: 'text-center',
                render: (data) => {
                  let color = analysis.format.tolerance(analysis.settings.tolerance.request.memory, data);
                  return `<span class="badge bg-${color.bg} text-${color.text}">${analysis.format.size(data, 'MB')}</span>`;
                }
              },
              {
                data: "profile_count",
                title: "Count",
                className: 'text-center',
                render: (data) => {
                  let color = analysis.format.tolerance(analysis.settings.tolerance.request.profile_count, data);
                  return `<span class="badge bg-${color.bg} text-${color.text}">${analysis.format.number(data, 0)}</span>`;
                }
              },
              {
                data: "http_code",
                title: "Code",
                className: 'text-center',
                render: (data) => {
                  let color = analysis.format.tolerance(analysis.settings.tolerance.request.http_code, data);
                  return `<span class="badge bg-${color.bg} text-${color.text}">${data}</span>`;
                }
              },
              {
                data: "method",
                title: "Method",
                className: 'text-end'
              },
              {
                data: "url",
                title: "URI"
              },
              {
                data: "error_count",
                title: `<i class="fa fa-exclamation-circle"></i>`,
                render: (data) => {
                  if (data === 0) return '';
                  return `<span class="badge bg-danger text-white">${data}</span>`;
                }
              }
            ],
            options: {
              order: [[0, 'desc']],
              pageLength: dtPageLength()
            },
            click: (event) => {
              let id = null, target = event.target;

              // Capture change page or change order, and force update data
              if (target.hasAttribute('data-dt-idx') && !target.hasAttribute('aria-current')) {
                setTimeout(() => this.updateData(), 10);
                return;
              }
              if (target.nodeName === 'TH' && target.classList.contains('sorting')) {
                setTimeout(() => this.updateData(), 10);
                return;
              }

              while (target && target.nodeName !== 'TR') {
                target = target.parentNode;
              }
              if (!target) {
                return;
              }

              id = this?.$refs['dt-reqs']?.$refs.dt.dt?.row(target).data()?.id;
              id && window.open(analysis.geturl('request', {id}), '_blank');
            }
          },
          keys: {
            columns: [
              {
                data: "key",
                title: "Request Key",
                render(data) {
                  return data;
                }
              },
              {
                data: "hits",
                title: "Hits",
                className: 'text-center',
                render: (data) => {
                  return analysis.format.number(data, 0);
                }
              },
              {
                data: "avg",
                title: "Avg",
                className: 'text-center',
                render: (data) => {
                  let color = analysis.format.tolerance(analysis.settings.tolerance.request.duration, data * 1000);
                  return `<span class="badge bg-${color.bg} text-${color.text}">${analysis.format.second(data)}</span>`;
                }
              },
              {
                data: "min",
                title: "Min",
                className: 'text-center min',
                render: (data) => {
                  let color = analysis.format.tolerance(analysis.settings.tolerance.request.duration, data * 1000);
                  return `<span class="badge bg-${color.bg} text-${color.text}">${analysis.format.second(data)}</span>`;
                }
              },
              {
                data: "max",
                title: "Max",
                className: 'text-center max',
                render: (data) => {
                  let color = analysis.format.tolerance(analysis.settings.tolerance.request.duration, data * 1000);
                  return `<span class="badge bg-${color.bg} text-${color.text}">${analysis.format.second(data)}</span>`;
                }
              },
              {
                data: "last",
                title: "Last",
                className: 'text-center last',
                orderable: false,
                render: (data) => {
                  let color = analysis.format.tolerance(analysis.settings.tolerance.request.duration, data * 1000);
                  return `<span class="badge bg-${color.bg} text-${color.text}">${analysis.format.second(data)}</span>`;
                }
              }
            ],
            options: {
              order: [[2, 'desc']],
              pageLength: dtPageLength()
            },
            click: (event) => {
              let id = null, target = event.target;

              // Capture change page or change order, and force update data
              if (target.hasAttribute('data-dt-idx') && !target.hasAttribute('aria-current')) {
                setTimeout(() => this.updateData(), 10);
                return;
              }
              if (target.nodeName === 'TH' && target.classList.contains('sorting')) {
                setTimeout(() => this.updateData(), 10);
                return;
              }

              if (!target.classList.contains('badge')) {
                return;
              }

              while (target && target.nodeName !== 'TD') {
                target = target.parentNode;
              }
              if (!target) {
                return;
              }

              let row = this?.$refs['dt-keys']?.$refs.dt.dt?.row(target).data();
              if (target.classList.contains('min')) {
                id = row?.min_id;
              } else if (target.classList.contains('max')) {
                id = row?.max_id;
              } else if (target.classList.contains('last')) {
                id = row?.last_id;
              }

              id && window.open(analysis.geturl('request', {id}), '_blank');
            }
          }
        }
      }
    }
  },
  mounted() {
    this.updateData();
    window.addEventListener('resize', () => this.resize());
    for (let i of document.querySelectorAll('#settings input, #settings select')) {
      i.addEventListener('change', analysis.saveSettings);
    }
  }
}
</script>

<style>
#dt-keys .min .badge,
#dt-keys .max .badge,
#dt-keys .last .badge,
#dt-reqs tbody tr {
  cursor: pointer;
  transition: all 0.2s ease-in-out;
}

#dt-keys .min .badge:hover,
#dt-keys .max .badge:hover,
#dt-keys .last .badge:hover,
#dt-reqs tbody tr:hover .badge {
  transform: scale(1.15);
}

#dt-reqs tbody tr:hover {
  transform: scale(1.01);
}

#dt-reqs tbody tr:hover td {
  background: rgb(13, 110, 253);
  color: #fff
}

#dt-keys td:nth-child(2),
#dt-keys td:nth-child(3),
#dt-keys td:nth-child(4),
#dt-keys td:nth-child(5),
#dt-keys td:nth-child(6) {
  width: 1%;
  min-width: 64px
}

#dt-reqs td:nth-child(1) {
  width: 1%;
  min-width: 110px
}

#dt-reqs td:nth-child(2),
#dt-reqs td:nth-child(3),
#dt-reqs td:nth-child(4),
#dt-reqs td:nth-child(5),
#dt-reqs td:nth-child(6) {
  width: 1%;
  min-width: 64px
}

#dt-reqs td:nth-child(8) {
  width: 1%
}
</style>

<style scoped>
.input-group-text {
  padding: 0;
  justify-content: center;
  width: 50px;
  font-size: 80%;
  font-weight: bold
}

input + .input-group-text {
  width: 30px
}

#current,
#chart-server,
#chart-request {
  min-height: 235px;
  max-height: 235px;
  overflow: auto;
}

#current-charts {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: center;
  max-height: 235px;
}

#current-charts > canvas {
  height: calc(100% / 3) !important;
  width: auto !important
}

#dts > div {
  height: calc(100vh - 260px);
  min-height: 315px;
}

@media (max-width: 767px) {
  #current-charts {
    flex-direction: row;
  }

  #current-charts > canvas {
    height: auto !important;
    width: calc(100% / 3) !important
  }

  #dts > div {
    max-height: 315px;
    margin-bottom: 30px !important;
  }
}

#search tr:last-child select {
  display: none !important
}
</style>