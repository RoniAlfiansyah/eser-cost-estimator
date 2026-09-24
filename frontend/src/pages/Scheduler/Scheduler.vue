<template>
  <main class="scheduler-module">
    <h1 class="sr-only">Planning &amp; Scheduling</h1>
    <div class="scheduler-frame-shell">
      <iframe
        ref="schedulerFrame"
        :src="schedulerUrl"
        title="ESER Planning and Scheduling"
        class="scheduler-frame"
        @load="handleFrameLoad"
      />
    </div>
  </main>
</template>

<script>
import config from '@/config';

const allowedSections = [
  'overview',
  'activities',
  'relationships',
  'resources',
  'calendar',
  'baselines',
  'reports',
  'master',
  'users',
];

export default {
  name: 'SchedulerPage',
  data() {
    return { frameReady: false };
  },
  computed: {
    activeSection() {
      const section = this.$route.params.section;
      return allowedSections.includes(section) ? section : 'activities';
    },
    schedulerUrl() {
      return `${config.hostApi}/scheduler/index.html?embedded=1&v=4.9.10`;
    },
  },
  watch: {
    activeSection(section) {
      this.navigateScheduler(section);
    },
  },
  mounted() {
    document.body.classList.add('scheduler-page-active');
    window.addEventListener('message', this.handleSchedulerMessage);
  },
  beforeDestroy() {
    document.body.classList.remove('scheduler-page-active');
    window.removeEventListener('message', this.handleSchedulerMessage);
  },
  methods: {
    handleFrameLoad() {
      this.frameReady = true;
      this.navigateScheduler(this.activeSection);
    },
    navigateScheduler(section) {
      const frame = this.$refs.schedulerFrame;
      if (!frame || !frame.contentWindow) return;
      frame.contentWindow.postMessage({ type: 'eser-scheduler-navigate', view: section }, '*');
    },
    handleSchedulerMessage(event) {
      if (!this.$refs.schedulerFrame || event.source !== this.$refs.schedulerFrame.contentWindow) return;
      const message = event && event.data;
      if (message && message.type === 'eser-auth-required') {
        this.$router.push('/login');
        return;
      }
      if (this.frameReady && message && message.type === 'eser-scheduler-view-changed' && allowedSections.includes(message.view)) {
        if (message.view !== this.activeSection) this.$router.push(`/app/scheduler/${message.view}`);
        return;
      }
      if (!message || message.type !== 'eser-basic-cost-created' || !message.id) return;
      this.$router.push(`/app/basic-costs/${message.id}/edit`);
    },
  },
};
</script>

<style lang="scss" scoped>
.scheduler-module { width: 100%; }
.sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
.scheduler-frame-shell { width:100%; background:#f4f7f9; overflow:hidden; }
.scheduler-frame { display:block; width:100%; height:calc(100vh - 108px); min-height:680px; border:0; background:#f4f7f9; }
@media (max-width: 768px) { .scheduler-frame { height:calc(100vh - 84px); min-height:620px; } }
</style>

<style lang="scss">
body.scheduler-page-active .content { padding: 12px 18px 20px; }
body.scheduler-page-active .contentFooter { display: none; }
@media (max-width: 767.98px) {
  body.scheduler-page-active .content { padding: 8px; }
}
</style>
