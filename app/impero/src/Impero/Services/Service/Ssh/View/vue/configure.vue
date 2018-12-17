<template>
    <div>
        <h2>Configure SSH</h2>
        <div class="form-group">
            <label>SSH port</label>
            <div>
                <input type="text" class="form-control" v-model="settings[myService.service].sshPort"/>
            </div>
        </div>
        <div class="form-group">
            <label>Permit login</label>
            <div>
                <input type="checkbox" value="1" v-model="settings[myService.service].permitRootLogin"/>
            </div>
        </div>
        <div class="form-group">
            <label>Login grace time</label>
            <div>
                <input type="number" class="form-control" v-model="settings[myService.service].loginGraceTime"/>
            </div>
        </div>
        <div class="form-group">
            <label>Password authentication</label>
            <div>
                <input type="checkbox" value="1" v-model="settings[myService.service].passwordAuthentication"/>
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
        name: 'impero-service-configure-ssh',
        props: {
            server: {},
            service: {}
        },
        data: function () {
            return {
                myServer: typeof this.server !== 'object' ? new Server(this.server) : this.server,
                myService: typeof this.service !== 'object' ? new Service(this.service) : this.service,
            };
        },
        computed: {
            settings: function () {
                return this.myServer.settings2;
            }
        }
    }
</script>