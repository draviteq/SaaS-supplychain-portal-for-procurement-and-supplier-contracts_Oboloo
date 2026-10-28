<template>
    <VDialog v-model="props.isViewDialogVisible" width="500">
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="closeDialog" />
  
      <!-- Dialog Content -->
      <VCard :title="$t(name)">
        <VCardText>
          {{ $t(comment) }}
        </VCardText>
        <VCardText class="d-flex justify-end">
          <VBtn @click="closeDialog">
            {{ $t('Close') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
  </template>
  
  <script setup>
  import { ref, watch} from "vue";
  import axiosIns from "@axios";
  import { useSnackBarStore } from "@core/utils/snackBar";
  import { useSavingsStore } from "@/router/stores/useSavingsStore";
  
  const savingViewComment = useSavingsStore();
  const snackBarStore = useSnackBarStore();
  
  const props = defineProps({
    isViewDialogVisible: {
      type: Boolean,
      required: true,
    },
    id: {
      type: Number,
      default: null,
    },
  });
  
  const emit = defineEmits(['update:isViewDialogVisible']);
  
  watch(() => props.isViewDialogVisible, (newValue) => {
    if (newValue) {
      fetchData();
    }
  });
  
  const fetchData = () => {
    if (props.id !== null) {
      try {
        let url = `/savings/view?id=${props.id}`;
        axiosIns
          .post(url)
          .then((res) => {
  
            name.value = res.data.data.users.name;
            comment.value = res.data.data.comment;
          })
          .catch((err) => {
            console.log(err);
          });
      } catch (error) {
        console.error("Error fetching data:", error);
      }
    }
  };
  
  const closeDialog = () => {
    emit('update:isViewDialogVisible', false);
  };
  
  const name = ref('');
  const comment = ref('');
  </script>
  