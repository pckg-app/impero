<template>
    <div class="impero-servers-one-component">
        <h1>{{ server.name }} / {{ server.ip }} / {{ server.status }}</h1>
        <h6>#web + #db + #cron + #mail</h6>
        <span v-for="tag in server.tags">#${ tag.tag } </span>

        <div>
            <!-- Nav tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation">
                    <a href="#services" aria-controls="services" role="tab" data-toggle="tab">Services and
                        dependencies</a>
                </li>
                <li role="presentation">
                    <a href="#applications" aria-controls="applications" role="tab" data-toggle="tab">Applications</a>
                </li>
                <li role="presentation">
                    <a href="#deployments" aria-controls="deployments" role="tab" data-toggle="tab">Deployments</a>
                </li>
                <li role="presentation">
                    <a href="#jobs" aria-controls="jobs" role="tab" data-toggle="tab">Jobs</a>
                </li>
                <li role="presentation">
                    <a href="#logs" aria-controls="logs" role="tab" data-toggle="tab">Logs</a>
                </li>
                <li role="presentation">
                    <a href="#notifications" aria-controls="logs" role="tab" data-toggle="tab">Notifications</a>
                </li>
                <li role="presentation">
                    <a href="#network" aria-controls="network" role="tab" data-toggle="tab">Network and firewall</a>
                </li>
                <li role="presentation">
                    <a href="#tasks" aria-controls="tasks" role="tab" data-toggle="tab">Tasks</a>
                </li>
            </ul>

            <!-- Tab panes -->
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane" id="services">
                    <button class="btn btn-success btn-xs"
                            title="Refresh services"
                            @click="server.refreshServicesStatuses()"><i
                            class="fa fa-refresh"></i></button>

                    <button class="btn btn-success btn-xs"
                            title="Install services"
                            @click.prevent="openInstallServicesPopup"><i
                            class="fa fa-plus"></i></button>

                    <div class="row">
                        <div class="col-md-6">

                            <div>
                                <pckg-bootstrap-modal :visible="installServicesModal"
                                                      @close="installServicesModal = false">
                                    <div slot="header">
                                        <h4>Install services</h4>
                                    </div>
                                    <div slot="body">
                                        <p>Select services you wish to install.</p>
                                        <ul>
                                            <li v-for="service in allServices"
                                                v-if="!service.isInstalledOnServer(server)">
                                                {{ service.name }} <a class="btn btn-xs"
                                                                      :href="service.getInstallOnServerUrl(server)">
                                                <i class="fa fa-plus"></i>
                                            </a>
                                            </li>
                                        </ul>
                                    </div>
                                </pckg-bootstrap-modal>
                            </div>

                            <h3>Services</h3>

                            <table class="table table-condensed table-striped">
                                <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="service in server.services">
                                    <td>{{ service.name }}</td>
                                    <td>{{ service.version }}</td>
                                    <td>

                                        <template
                                                v-if="true || hasComponent('impero-service-configure-' + service.service)">

                                            <button class="btn btn-warning btn-xs"
                                                    @click="configureModal = service.service"><i class="fa fa-cogs"></i></button>

                                            <pckg-bootstrap-modal :visible="configureModal == service.service"
                                                                  @close="configureModal == service.service ? configureModal = null : null">
                                                <div slot="body" v-if="configureModal == service.service">
                                                    <component :is="'impero-service-configure-' + service.service"
                                                               :server="server"
                                                               :service="service"></component>
                                                </div>
                                            </pckg-bootstrap-modal>

                                        </template>

                                        {{ service.status }}
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h3>Dependencies</h3>
                            <table class="table table-condensed table-striped">
                                <thead>
                                <tr>
                                    <th>Dependency</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="dependency in server.dependencies">
                                    <td>{{ dependency.name }}</td>
                                    <td>{{ dependency.version }}</td>
                                    <td>
                                        <!--<button class="btn btn-success btn-xs"
                                                @click="dependency.refreshStatus()"><i
                                                class="fa fa-refresh"></i></button>-->
                                        {{ dependency.status_id }}
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="applications">
                    <div class="row">
                        <table class="table table-condensed table-striped">
                            <thead>
                            <tr>
                                <th>Website</th>
                                <th>Application</th>
                                <th>Url</th>
                                <th>Https</th>
                                <th>Source</th>
                                <th>Version</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="website in server.websites">
                                <td>{{ website.name }}</td>
                                <td>{{ website.name }}</td>
                                <td>
                                    {{ website.url }}<br/>
                                    <span v-for="(url, i) in website.urls">{{ url }}<br v-if="i + 1 < website.urls.length"/></span>
                                </td>
                                <td>{{ website.https }}</td>
                                <td>{{ website.source }}</td>
                                <td>{{ website.version }}</td>
                                <td>{{ website.status }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="deployments">
                    <table class="table table-condensed table-striped">
                        <thead>
                        <tr>
                            <th>Application</th>
                            <th>Started at</th>
                            <th>Ended at</th>
                            <th>Version</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="deployment in server.deployments">
                            <td>${ deployment.application.name }</td>
                            <td>${ deployment.started_at | datetime }</td>
                            <td>${ deployment.ended_at | datetime }</td>
                            <td>${ deployment.version }</td>
                            <td>${ deployment.status }</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div role="tabpanel" class="tab-pane" id="jobs">
                    <table class="table table-condensed table-striped">
                        <thead>
                        <tr>
                            <th>User</th>
                            <th>Name</th>
                            <th>Command</th>
                            <th>Frequency</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="job in server.jobs">
                            <td>${ job.user }</td>
                            <td>${ job.name }</td>
                            <td>${ job.command }</td>
                            <td>${ job.frequency }</td>
                            <td>${ job.status }</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div role="tabpanel" class="tab-pane" id="logs">
                    <table class="table table-condensed table-striped">
                        <thead>
                        <tr>
                            <th>Log</th>
                            <th>Datetime</th>
                            <th>Description</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="log in server.logs">
                            <td>${ log.name }</td>
                            <td>${ log.created_at | datetime }</td>
                            <td>${ log.description }</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div role="tabpanel" class="tab-pane" id="notifications">
                    <table class="table table-condensed table-striped">
                        <thead>
                        <tr>
                            <th>Notification</th>
                            <th>Datetime</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="log in server.logs">
                            <td>${ log.name }</td>
                            <td>${ log.created_at | datetime }</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div role="tabpanel" class="tab-pane" id="network">
                    <div class="row">
                        <div class="col-md-6">
                            <h3>Network interfaces</h3>

                            <table class="table table-condensed table-striped">
                                <thead>
                                <tr>
                                    <th>Interface</th>
                                    <th>IP</th>
                                    <th>Transfer (D/U)</th>
                                    <th>Mask</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="iface in server.networkInterfaces">
                                    <td>{{ iface.name }}</td>
                                    <td>{{ iface.ipv4 }}<br/>{{ iface.ipv6 }}</td>
                                    <td>{{ iface.downloaded }} / {{ iface.uploaded }}</td>
                                    <td>{{ iface.mask }}</td>
                                </tr>
                                </tbody>
                            </table>

                        </div>
                        <div class="col-md-6">
                            <h3>Firewall settings</h3>
                            <table class="table table-condensed table-striped">
                                <thead>
                                <tr>
                                    <th>Rule</th>
                                    <th>From</th>
                                    <th>Service / Port</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="rule in server.firewallSettings">
                                    <td>{{ rule.rule }}</td>
                                    <td>{{ rule.from }}</td>
                                    <td>{{ rule.port }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="tasks">
                    <div class="row">
                        <div class="col-md-6">
                            <h3>Tasks</h3>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
    import {Server, Servers} from "../../../../Pckg/Generic/public/impero";

    export default {
        name: 'impero-servers-one',
        mixins: [pckgDelimiters],
        props: {
            id: Number
        },
        data: function () {
            return {
                server: new Server(),
                installServicesModal: false,
                configureModal: null
            };
        },
        methods: {
            hasComponent(comp) {
                return this.$root.$options.components[comp] || false;
            },
            fetchServer: function () {
                (new Servers()).one(this.id).then(function (server) {
                    console.log('got server', server, JSON.parse(server.toJSON()));
                    // this.server = server;
                    this.server = server;
                    console.log("fetched", this, this.$root, $vue);
                }.bind(this));
            },
            openInstallServicesPopup: function () {
                /**
                 * Get and show list of available services to install.
                 * Start flow for each separate service (haproxy requires one flow, mysql requires another, ...).
                 */
                this.installServicesModal = true;
            }
        },
        computed: {
            allServices: function () {
                /**
                 * @T00D00 - how to
                 */
                // console.log('calling allServices');
                return $store.getters.services;
                // return Impero.Servers.Entity.Services.getAllServices();
            },
            services: function () {
                return this.server.services
                    ? this.server.services
                    : [];
            },
            installedServices: function () {
                return this.services.filter(function (service) {
                    return service.installed == 'yes';
                });
            },
            availableServices: function () {
                return this.services.filter(function (service) {
                    return service.installed == 'no';
                });
            }
        },
        created: function () {
            this.fetchServer();
            $store.dispatch('prepareServices');
        }
    }
</script>