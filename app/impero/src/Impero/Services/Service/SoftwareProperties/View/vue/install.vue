<template>
    <div>
        <h2>Install Software Properties Common</h2>
    </div>
</template>

<script>
    import {Server, Service} from "../../../../../../Pckg/Generic/public/impero";

    export default {
        name: 'impero-service-install-software-properties-common',
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