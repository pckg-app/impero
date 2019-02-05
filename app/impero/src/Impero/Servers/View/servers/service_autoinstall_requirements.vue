<template>
    <ul>
        <li v-for="requirement in requirements">
            {{ requirement.name }}

            <template v-if="requirement.type == 'service'">
                <a v-if="requirement.requirements.length > 0" href="#" class="clr-error">fix all issues</a>
                <a v-if="!requirement.installed" href="#" class="clr-error">install locally</a>
            </template>
            <template v-else-if="requirement.type == 'resource'">
                <a v-if="requirement.requirements.length > 0" href="#" class="clr-error">fix all issues</a>
                <a v-if="!requirement.installed" href="#" class="clr-error">install locally</a>
                <a v-if="!requirement.installed" href="#" class="clr-error">install on remote</a>
                <a v-if="!requirement.installed" href="#" class="clr-error">select remote</a>
            </template>
            <template v-else-if="requirement.type == 'network'">
                <a v-if="requirement.requirements.length > 0" href="#" class="clr-error">fix all issues</a><br />
                {{ requirement.settings.from }} -> {{ requirement.settings.to }}:{{ requirement.settings.port }}
            </template>

            <service-autoinstall-requirements v-if="requirement.requirements.length > 0"
                                              :server="server" :service="service"
                                              :requirements="requirement.requirements"></service-autoinstall-requirements>
        </li>
    </ul>
</template>

<script>
    export default {
        name: 'service-autoinstall-requirements',
        props: {
            server: {},
            service: {},
            application: {},
            requirements: {}
        }
    }
</script>