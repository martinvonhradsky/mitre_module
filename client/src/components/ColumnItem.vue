<template lang="">
  <div
    class="rounded-lg shadow-md inline-flex items-center w-full border-black border mb-2 border-solid"
    :class="statusColorClass"
  >
    <div class="relative flex-grow justify-center items-center">
      <RouterLink
        :to="{ name: 'ItemDetail', params: { id: item.id } }"
        class="text-xs pl-1 link"
      >
        {{ item.name }}
      </RouterLink>
    </div>
  </div>
</template>

<script>
import { RouterLink } from "vue-router";

export default {
  name: "ColumnItem",
  components: {
    RouterLink,
  },
  props: {
    item: {
      type: Object,
      required: true,
    },
  },
  computed: {
    statusColorClass() {
      switch (this.item.startpage) {
        case "available":
          return "bg-white text-black";
        case "not available":
          return "bg-gray-400 text-black";
        case "detected":
          return "bg-green-500 text-white";
        case "executed":
          return "bg-red-500 text-white";
        default:
          return "";
      }
    },
  },
};
</script>

<style scoped>
.link {
  cursor: pointer;
  text-decoration: underline;
  text-decoration-color: inherit;
}

.link:visited {
  color: inherit;
  cursor: pointer;
  text-decoration: underline;
  text-decoration-color: inherit;
}
</style>
