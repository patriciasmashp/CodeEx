<template>
    <div class="ks-messages ks-messenger__messages" v-if="user">
        <div class="ks-header">
            <div class="ks-description">
                <div class="ks-name">{{ user.name }}</div>
                <div class="ks-amount">Последнее сообщение: {{ getFormatDate(user.last_message.complaint_date) }}</div>
            </div>
        </div>
        <div class="ks-body ks-scrollable jspScrollable" ref="scrollContainer" data-auto-height
            data-reduce-height=".ks-footer" data-fix-height="32" tabindex="0">
            <div class="jspContainer" style="width: 701px; height: 481px;">
                <div class="jspPane" style="padding: 0px; top: 0px; width: 691px;">
                    <ul class="ks-items" v-for="(message, index) in messages" v-bind:key="index">
                        <li class="ks-item ks-self" v-if="message.destination == 'master'">
                            <div class="ks-body">
                                <div class="ks-header">
                                    <span class="ks-datetime">{{ getFormatDate(message.complaint_date) }}</span>
                                </div>
                                <div class="ks-message">{{ message.text }}</div>
                                <div class="attachments" v-if="message.attachments.length !== 0">
                                    <AttachmentViewer :attachments="message.attachments" />
                                </div>
                            </div>

                            <div class="questionnaire" v-if="message.is_fisrt_appeal">
                                <div class="master_information">
                                    <div><b>Имя:</b> {{ message.master.name }}</div>
                                    <div><b>Номер:</b> {{ message.master.phone_number }}</div>
                                    <div><b>Имя пользователя:</b> {{ message.master.username }}</div>
                                    <div><b>Город:</b> {{ message.master.city }}</div>
                                    <div><b>Тип тату:</b> {{ message.master.tattoo_type }}</div>
                                    <div><b>О себе:</b> {{ message.master.about_master }}</div>
                                </div>
                                <ImagePreviewer :images="message.master.photos" />

                            </div>
                        </li>
                        <li class="ks-item ks-from" v-if="message.destination == 'client'">
                            <div class="ks-body">
                                <div class="ks-header">
                                    <span class="ks-datetime">{{ getFormatDate(message.complaint_date) }}</span>
                                </div>
                                <div class="ks-message">{{ message.text }}</div>
                                <div class="attachments" v-if="message.attachments.length !== 0">
                                    <AttachmentViewer :attachments="message.attachments" />
                                </div>
                            </div>
                        </li>

                    </ul>
                </div>
            </div>
        </div>
        <!-- <div class="atachment-to-send-container">
                <div class="file">
                    <font-awesome-icon icon="file-lines" />
                </div>
            </div> -->
        <div class="ks-footer ">

            <textarea class="form-control col-md-10" ref="textInput" :disabled="chat_closed"
                @keydown.enter.prevent="sendMessage" placeholder="Написать сообщение ..."></textarea>
            <div class="ks-controls col-md-1">
                <button v-on:click="sendMessage" class="btn btn-primary">Отправить</button>
                <div class="paperclip" @click="attachFile">
                    <font-awesome-icon icon="file-arrow-up" class="paper-clip" />
                </div>
            </div>
        </div>
    </div>
    <div id="hidden">
        <input type="file" @change="onFileChange" id="fileInput">
    </div>
</template>

<script>

import { mapState } from 'vuex';
import ImagePreviewer from './ImagePreviewer.vue';
import AttachmentViewer from './AttachmentViewer.vue'
import axios from 'axios';
import config from '@/config';
import Cookies from 'js-cookie'

export default {
    name: 'MessengerComponent',
    components: {
        ImagePreviewer,
        AttachmentViewer
    },
    data() {
        return {
            user: this.$store.getters.CURENT_USER,
            photos: Array,
            selectedFiles: [],
            chat_closed: function () {
                return false
            }
        }
    },
    methods: {
        getFormatDate: function (date) {
            date = new Date(date)

            return date.getDate() + "." + date.getMonth() + 1 + "."
                + date.getFullYear() + " " + date.getHours() + ":" + date.getMinutes()
        },
        sendMessage: function (e) {
            if (!this.$refs.textInput.value) {
                return
            }
            e.preventDefault()
            let data = {
                user_id: this.curent_user.client_id,
                text: this.$refs.textInput.value,
                destination: "client",
                master_id: String(this.curent_user.last_message.master_id)
            }
            axios.post(config.host_addres + config.routes.sendMessage, data, {
                headers: {
                    Authorization: `Bearer ${Cookies.get('token')}`
                }
            }).then(
                this.$refs.textInput.value = ""
            ).catch(error => {
                if (error.response.status == 401) {
                    this.$store.commit('SET_ISAUTHORIZED', false)
                }
                alert(`Хммм, что то пошло не так\n ${error}`)
            })
        },
        attachFile: function () {
            if (!this.$data.chat_closed) {
                let input = document.getElementById('fileInput')
                input.click()
            }
        },
        onFileChange(event) {
            let file = (event.target.files[0])
            const formData = new FormData();
            formData.append('file', file);
            formData.append('client_id', this.user.client_id);
            formData.append('destination', 'client');
            formData.append('master_id', String(this.curent_user.last_message.master_id));

            axios.post(config.host_addres + config.routes.sendFile, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    Authorization: `Bearer ${Cookies.get('token')}`
                }
            })
        },
    },
    computed: {
        ...mapState({
            curent_user: state => state.curent_user,
            curent_userAlias: 'user',
            messages: state => state.messages
        }),

    },
    watch: {
        curent_user(newValue) {

                this.$data.user = newValue
                this.messages = []
                this.$store.dispatch('UNSUBSCRIBE_MESSAGES');

                this.$store.dispatch('GET_MESSAGES_STREAM', newValue.client_id);

        },
        messages() {
            if (this.messages[this.messages.length - 1].chat_closed) {
                this.$data.chat_closed = true
            }
            else {
                this.$data.chat_closed = false
            }
            setTimeout(() => {
                this.$refs.scrollContainer.scrollTop = this.$refs.scrollContainer.scrollHeight
            }, 200)
        }
    },
    mounted() {
        this.$store.dispatch('GET_MESSAGES_STREAM', this.user.client_id);
    },
    beforeUnmount() {
        this.$store.dispatch('UNSUBSCRIBE_MESSAGES');
    }

}


</script>

<style scoped>
.file {
    font-size: 3rem;
    padding: 0px 20px 0 20px;
    background-color: #cecece;
    border-radius: 20px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
}

#hidden {
    display: none;
}

.paper-clip {
    font-size: 1.4em;
    margin-left: 12px;
    color: #d1d3d7;
    transition: 0.2s;
    cursor: pointer;
}

.paper-clip:hover {
    color: #0d6efd;
}

.form-control {
    width: inherit;
}

.questionnaire {
    display: flex;
    margin-left: 14px;
    margin-right: 50px;
    margin-top: 10px;
    margin-bottom: 15px;
    border: solid 1px #dee0e1;
}

.questionnaire .master_information {
    width: 100%;
    padding: 13px;
}

.ks-items .ks-item {
    flex-direction: column;
}

.ks-messages .jspScrollable {
    height: 480px;
    overflow: scroll;
    padding: 0px;
    width: 100%;
    overflow-x: clip;
}

.ks-messenger .ks-messages,
.ks-messenger__messages {
    display: -webkit-box;
    display: -webkit-flex;
    display: -ms-flexbox;
    -js-display: flex;
    display: flex;
    -webkit-box-orient: vertical;
    -webkit-box-direction: normal;
    -webkit-flex-direction: column;
    -ms-flex-direction: column;
    flex-direction: column;
    -webkit-box-flex: 1;
    -webkit-flex-grow: 1;
    -ms-flex-positive: 1;
    flex-grow: 1;
    background: #fff
}

.ks-messenger .ks-messages>.ks-header,
.ks-messenger__messages>.ks-header {
    display: -webkit-box;
    display: -webkit-flex;
    display: -ms-flexbox;
    -js-display: flex;
    display: flex;
    -webkit-box-align: center;
    -webkit-align-items: center;
    -ms-flex-align: center;
    align-items: center;
    height: 60px;
    border-bottom: 1px solid #dee0e1;
    padding: 9px 20px;
    -webkit-box-pack: justify;
    -webkit-justify-content: space-between;
    -ms-flex-pack: justify;
    justify-content: space-between
}

.ks-messenger .ks-messages>.ks-header>.ks-description>.ks-name,
.ks-messenger__messages>.ks-header>.ks-description>.ks-name {
    font-size: 14px;
    line-height: 14px;
    margin-bottom: 5px;
    font-weight: 500
}

.ks-messenger .ks-messages>.ks-header>.ks-description>.ks-amount,
.ks-messenger__messages>.ks-header>.ks-description>.ks-amount {
    color: #858585;
    font-size: 12px;
    line-height: 12px
}

.ks-messenger .ks-messages>.ks-body,
.ks-messenger__messages>.ks-body {
    -webkit-box-flex: 1;
    -webkit-flex-grow: 1;
    -ms-flex-positive: 1;
    flex-grow: 1
}

.ks-messenger .ks-messages>.ks-body .ks-items,
.ks-messenger__messages>.ks-body .ks-items {
    list-style: none;
    padding: 0;
    margin: 0;
    padding: 20px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-separator,
.ks-messenger__messages>.ks-body .ks-items>.ks-separator {
    color: #858585;
    font-size: 10px;
    text-align: center;
    text-transform: uppercase;
    margin-bottom: 15px;
    margin-top: 15px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item,
.ks-messenger__messages>.ks-body .ks-items>.ks-item {
    display: -webkit-box;
    display: -webkit-flex;
    display: -ms-flexbox;
    -js-display: flex;
    display: flex;
    margin-bottom: 12px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item:last-child,
.ks-messenger__messages>.ks-body .ks-items>.ks-item:last-child {
    margin-bottom: 0
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body {
    font-size: 1.1em;
    -webkit-box-flex: 1;
    -webkit-flex-grow: 1;
    -ms-flex-positive: 1;
    flex-grow: 1;
    padding: 12px 15px;
    -webkit-border-radius: 2px;
    border-radius: 2px;
    position: relative
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-header,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-header {
    display: -webkit-box;
    display: -webkit-flex;
    display: -ms-flexbox;
    -js-display: flex;
    display: flex;
    -webkit-box-pack: justify;
    -webkit-justify-content: space-between;
    -ms-flex-pack: justify;
    justify-content: space-between;
    margin-bottom: 2px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-header>.ks-name,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-header>.ks-name {
    font-weight: 500
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-header>.ks-datetime,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-header>.ks-datetime {
    font-size: 10px;
    text-transform: uppercase;
    color: #858585
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-link,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-link {
    position: relative;
    margin-top: 10px;
    padding-left: 12px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-link:before,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-link:before {
    content: '';
    width: 4px;
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    background-color: rgba(57, 81, 155, .2)
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files {
    list-style: none;
    padding: 0;
    margin: 0
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file {
    float: left;
    margin-top: 15px;
    margin-right: 15px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a {
    display: block;
    color: #333;
    /* vertical-align: top; */
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info,
.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-thumb,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-thumb {
    float: left
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-thumb,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-thumb {
    margin-right: 5px;
    text-align: center
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-thumb>.ks-icon,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-thumb>.ks-icon {
    font-size: 36px;
    line-height: 36px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-thumb>img,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-thumb>img {
    width: 36px;
    height: 36px;
    -webkit-border-radius: 2px;
    border-radius: 2px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info>.ks-name,
.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info>.ks-size,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info>.ks-name,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info>.ks-size {
    display: block
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info>.ks-name,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info>.ks-name {
    font-size: 12px;
    margin-bottom: 2px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info>.ks-size,
.ks-messenger__messages>.ks-body .ks-items>.ks-item>.ks-body>.ks-message>.ks-files>.ks-file a>.ks-info>.ks-size {
    font-size: 10px;
    text-transform: uppercase;
    color: #858585
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-self>.ks-avatar,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-self>.ks-avatar {
    -webkit-box-ordinal-group: 2;
    -webkit-order: 1;
    -ms-flex-order: 1;
    order: 1
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-self>.ks-body,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-self>.ks-body {
    -webkit-box-ordinal-group: 3;
    -webkit-order: 2;
    -ms-flex-order: 2;
    order: 2;
    border: solid 1px #dee0e1;
    -webkit-border-top-left-radius: 0;
    border-top-left-radius: 0;
    margin-left: 14px;
    margin-right: 50px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-self>.ks-body:before,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-self>.ks-body:before {
    content: '';
    display: block;
    position: absolute;
    left: -10px;
    top: -1px;
    width: 0;
    height: 0;
    border-top: 10px solid #dee0e1;
    border-right: 0 solid transparent;
    border-bottom: 0 solid transparent;
    border-left: 10px solid transparent
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-self>.ks-body:after,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-self>.ks-body:after {
    content: '';
    display: block;
    position: absolute;
    left: -8px;
    top: 0;
    width: 0;
    height: 0;
    border-top: 10px solid #fff;
    border-right: 0 solid transparent;
    border-bottom: 0 solid transparent;
    border-left: 10px solid transparent
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-from>.ks-avatar,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-from>.ks-avatar {
    -webkit-box-ordinal-group: 3;
    -webkit-order: 2;
    -ms-flex-order: 2;
    order: 2
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-from>.ks-body,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-from>.ks-body {
    -webkit-box-ordinal-group: 2;
    -webkit-order: 1;
    -ms-flex-order: 1;
    order: 1;
    background-color: #cafacc87;
    -webkit-border-top-right-radius: 0;
    border-top-right-radius: 0;
    margin-right: 14px;
    margin-left: 50px
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-from>.ks-body:before,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-from>.ks-body:before {
    content: '';
    display: block;
    position: absolute;
    right: -10px;
    top: 0;
    width: 0;
    height: 0;
    border-top: 0 solid transparent;
    border-right: 0 solid transparent;
    border-bottom: 10px solid transparent;
    border-left: 10px solid #eff1f7
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-unread>.ks-body,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-unread>.ks-body {
    background: #fcf8e7
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-unread.ks-self>.ks-body:after,
.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-unread.ks-self>.ks-body:before,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-unread.ks-self>.ks-body:after,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-unread.ks-self>.ks-body:before {
    border-top: 10px solid #fcf8e7
}

.ks-messenger .ks-messages>.ks-body .ks-items>.ks-item.ks-unread.ks-from>.ks-body:before,
.ks-messenger__messages>.ks-body .ks-items>.ks-item.ks-unread.ks-from>.ks-body:before {
    border-left: 10px solid #fcf8e7
}

.ks-messenger .ks-messages>.ks-footer,
.ks-messenger__messages>.ks-footer {
    padding: 15px 20px;
    border-top: 1px solid #dee0e1;
    display: -webkit-box;
    display: -webkit-flex;
    display: -ms-flexbox;
    -js-display: flex;
    display: flex
}

.ks-messenger .ks-messages>.ks-footer>.form-control,
.ks-messenger__messages>.ks-footer>.form-control {
    -webkit-box-flex: 1;
    -webkit-flex-grow: 1;
    -ms-flex-positive: 1;
    flex-grow: 1;
    height: 38px;
    overflow: hidden;
    resize: none;
    margin-right: 20px
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls,
.ks-messenger__messages>.ks-footer>.ks-controls {
    text-align: right;
    width: auto;
    display: -webkit-box;
    display: -webkit-flex;
    display: -ms-flexbox;
    -js-display: flex;
    display: flex;
    -webkit-box-align: center;
    -webkit-align-items: center;
    -ms-flex-align: center;
    align-items: center
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls .ks-attachment,
.ks-messenger .ks-messages>.ks-footer>.ks-controls .ks-smile,
.ks-messenger__messages>.ks-footer>.ks-controls .ks-attachment,
.ks-messenger__messages>.ks-footer>.ks-controls .ks-smile {
    font-size: 22px;
    color: #8997c3;
    line-height: 22px;
    margin-left: 25px
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls>.dropdown,
.ks-messenger__messages>.ks-footer>.ks-controls>.dropdown {
    display: inline-block
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls>.dropdown>.ks-smile,
.ks-messenger__messages>.ks-footer>.ks-controls>.dropdown>.ks-smile {
    padding: 0
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys,
.ks-messenger__messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys {
    width: 200px;
    height: 167px
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys .ks-smileys-wrapper,
.ks-messenger__messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys .ks-smileys-wrapper {
    padding: 10px
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys table,
.ks-messenger__messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys table {
    width: 100%
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys table td,
.ks-messenger__messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys table td {
    vertical-align: middle;
    text-align: center;
    padding-bottom: 10px;
    cursor: pointer
}

.ks-messenger .ks-messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys table tr:last-child td,
.ks-messenger__messages>.ks-footer>.ks-controls>.dropdown>.ks-smileys table tr:last-child td {
    padding-bottom: 0
}

.ks-messenger .ks-messages>.ks-files,
.ks-messenger__messages>.ks-files {
    list-style: none;
    padding: 0;
    margin: 0;
    padding: 20px;
    padding-top: 0;
    padding-bottom: 10px;
    margin-top: -10px
}

.ks-messenger .ks-messages>.ks-files>.ks-file,
.ks-messenger__messages>.ks-files>.ks-file {
    display: inline-block;
    cursor: pointer;
    margin-right: 10px;
    margin-top: 10px;
    position: relative
}

.ks-messenger .ks-messages>.ks-files>.ks-file:hover>.ks-thumb,
.ks-messenger__messages>.ks-files>.ks-file:hover>.ks-thumb {
    border: solid 1px #42a5f5
}

.ks-messenger .ks-messages>.ks-files>.ks-file>.ks-thumb,
.ks-messenger__messages>.ks-files>.ks-file>.ks-thumb {
    width: 90px;
    height: 90px;
    -webkit-border-radius: 2px;
    border-radius: 2px;
    background-color: #fff;
    border: solid 1px #dee0e1;
    margin-bottom: 5px;
    display: -webkit-box;
    display: -webkit-flex;
    display: -ms-flexbox;
    -js-display: flex;
    display: flex;
    -webkit-box-align: center;
    -webkit-align-items: center;
    -ms-flex-align: center;
    align-items: center;
    text-align: center;
    font-size: 45px;
    color: #25628f
}

.ks-messenger .ks-messages>.ks-files>.ks-file>.ks-thumb::before,
.ks-messenger__messages>.ks-files>.ks-file>.ks-thumb::before {
    width: 100%
}

.ks-messenger .ks-messages>.ks-files>.ks-file>img.ks-thumb,
.ks-messenger__messages>.ks-files>.ks-file>img.ks-thumb {
    border: none
}

.ks-messenger .ks-messages>.ks-files>.ks-file>.ks-name,
.ks-messenger__messages>.ks-files>.ks-file>.ks-name {
    display: block;
    font-size: 12px;
    font-weight: 400;
    color: #333
}

.ks-messenger .ks-messages>.ks-files>.ks-file>.ks-size,
.ks-messenger__messages>.ks-files>.ks-file>.ks-size {
    position: relative;
    top: -2px;
    font-size: 10px;
    color: #858585
}

@media screen and (max-width:800px) {

    .ks-messenger .ks-messages,
    .ks-messenger__messages {
        position: fixed;
        top: 120px;
        bottom: 0;
        z-index: 2;
        height: -webkit-calc(100% - 120px);
        height: calc(100% - 120px);
        width: 100%;
        right: -1000px
    }

    .ks-messenger .ks-messages.ks-open,
    .ks-messenger__messages.ks-open {
        right: 0;
        -webkit-transition: right .2s ease;
        transition: right .2s ease
    }
}

@media screen and (max-width:560px) {

    .ks-messenger .ks-messages>.ks-footer,
    .ks-messenger__messages>.ks-footer {
        -webkit-box-orient: vertical;
        -webkit-box-direction: normal;
        -webkit-flex-direction: column;
        -ms-flex-direction: column;
        flex-direction: column
    }

    .ks-messenger .ks-messages>.ks-footer textarea,
    .ks-messenger__messages>.ks-footer textarea {
        margin-bottom: 20px
    }
}

#input-group-icon-text {
    height: 10px;
}
</style>
