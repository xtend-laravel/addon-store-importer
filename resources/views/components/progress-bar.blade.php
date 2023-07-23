<div
  x-data="{
    interval: null,
    progress: 0,
    init() {
      $nextTick(() => {
        this.interval = setInterval(() => {
          this.progress = this.progress + 1
          if (this.progress >= 100) {
            clearInterval(this.interval)
          }
        }, 100)
      })
    }
  }"
  class="w-full bg-gray-200 rounded-full dark:bg-neutral-600 mr-10">
  <div
    class="p-0.5 text-center rounded-full transition-all duration-500 text-xs font-medium leading-none text-white"
    x-bind:class="{
      'bg-green-500': progress >= 80,
      'bg-amber-500': progress < 100 && progress > 50,
      'bg-gray-500': progress <= 50
    }"
    x-bind:style="'width: ' + progress + '%'"
  >
    <span x-text="progress + '%'"></span>
  </div>
</div>
