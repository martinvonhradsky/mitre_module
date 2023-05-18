<template lang="">
  <div>
    <div class="px-1 border-black border-b border-solid mb-2">
      <div class="">
        <h1 class="text-sm">{{ name }}</h1>
        <p class="text-xs">{{ itemCount }} techniques</p>
      </div>
    </div>
  </div>
</template>
<script>
export default {
  name: "CategoryHeader",
  props: {
    name: {
      type: String,
      required: true,
    },
    itemCount: {
      type: Number,
      required: true,
    },
  },
};
</script>
