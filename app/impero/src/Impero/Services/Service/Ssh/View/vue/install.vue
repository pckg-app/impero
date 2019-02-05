<template>
    <div>
        <h2>Install SSH</h2>
        <p>SSH service should already be installed on your system, otherwise we cannot instantiate connection.</p>
        <h3>SSH Configuration</h3>
        <div class="form-group">
            <label>SSH port</label>
            <div>
                <input type="text" class="form-control" v-model="settings[myService.service].sshPort"/>
            </div>
        </div>
        <div class="form-group">
            <div>
                <button type="button" class="btn btn-success" @click.prevent="myService.saveServerSettings(myServer)">Save</button>
            </div>
        </div>
    </div>
</template>

<script>
    import {Server, Service} from "../../../../../../Pckg/Generic/public/impero";

    export default {
        name: 'impero-service-install-ssh',
        props: {
            server: {},
            service: {}
        },
        data: function () {
            return {
                myServer: typeof this.server !== Server ? new Server(this.server) : this.server,
                myService: typeof this.service !== Service ? new Service(this.service) : this.service,
            };
        },
        computed: {
            settings: function () {
                return this.myServer.settings2;
            }
        }
    }
</script>