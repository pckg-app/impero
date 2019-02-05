<template>

    <div>
        <button type="button" class="btn btn-default" @click.prevent="modal = 'addNewApplication'">
            <i class="fa fa-plus">Add new application</i>
        </button>

        <pckg-bootstrap-modal :visible="modal == 'addNewApplication'">
            <div slot="body">

                <service-autoinstall></service-autoinstall>

                <hr />

                <div class="form-group">
                    <label>Application</label>
                    <div>
                        mailo.foobar.si
                    </div>
                </div>

                <div class="form-group">
                    <label>Service</label>
                    <div>
                        <pckg-select :initial-options="initialServices" :initial-multiple="false"></pckg-select>
                    </div>
                </div>

                <div class="help">We would like to create new RabbitMq services on existing infrastructure.</div>

                <button type="button" class="btn btn-default">Install RabbitMq on one.gonparty.eu</button>

                <p class="clr-success">Service has been installed.</p>

                <button type="button" class="btn btn-default">Remove cron:newsletter service from one.gonparty.eu</button>

                <p class="clr-success">Cron has been removed.</p>

                <button type="button" class="btn btn-default">Add sendmail:newsletter to one.gonparty.eu</button>

                <p class="clr-success">Service has been started</p>

                <p>Select new or existing service you'd like to scale up</p>

                <p>SendMailNewsletter jobs requires additional module (php-imap) and sendmail service installed on
                    server.</p>

                <button type="button" class="btn btn-default">Install php-imap PHP module on two.gonparty.eu</button>

                <button type="button" class="btn btn-default">Install sendmail on two.gonparty.eu</button>

                <p class="clr-success">Module successfully established.</p>

                <p>Web service on two.gonparty.eu requires communication with master database on zero.gonparty.eu:3306
                    and slave on one.gonparty.eu:3306.</p>

                <button type="button" class="btn btn-default">Connect two.gonparty.eu and zero.gonparty.eu via VPN
                </button>

                <button type="button" class="btn btn-default">Connect two.gonparty.eu and one.gonparty.eu via VPN
                </button>

                <p class="clr-success">Network connection successfully established.</p>

                <p>SendMailNewsletter job requires connection with RabbitMQ master on zero.gonparty.eu:1111</p>

                <button type="button" class="btn btn-default">Open port 1111 on existing VPN connection</button>

                <p class="clr-success">Port successfully opened.</p>

                <p>Web service requires storage to be mounted</p>

                <button type="button" class="btn btn-default">Mount remote FRA01-01 from zero.gonparty.eu to
                    two.gonparty.eu via NFS
                </button>

                <p class="clr-success">Project checked out, configuration dumped.</p>

                <p>SendMailNewsletter requires web service to be checked out</p>

                <button type="button" class="btn btn-default">Checkout project</button>

                <p class="clr-success">Project checked out, configuration dumped.</p>

                <p>SendMailNewsletter service can now be deployed to two.gonparty.eu</p>

                <button type="button" class="btn btn-default">Deploy service</button>

                <p class="clr-success">Service deployed.</p>

            </div>
        </pckg-bootstrap-modal>

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

</template>

<script>
    export default {
        name: 'derive-servers-one-applications',
        props: {
            server: {
                required: true,
                type: Object
            }
        },
        data: function () {
            return {
                modal: null,
                initialServices: { // read from pckg.yaml
                    web: 'Web',
                    cron: 'Cron',
                    db: 'Database (slave)',
                    mail: 'Sendmail',
                }
            };
        }
    }
</script>