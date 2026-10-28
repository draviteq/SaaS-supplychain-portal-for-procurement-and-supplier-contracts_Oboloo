<script setup>
import { avatarText } from '@core/utils/formatters'
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { useSnackBarStore } from "@core/utils/snackBar";
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import { formatDate } from "@/plugins/date/dateUtils"
import { scrollTop } from "@/plugins/dashboards/utils";
import useEmitter from "@/plugins/emitter/useEmitter"

const savingStore = useSavingsStore()
const snackBarStore = useSnackBarStore();
const emitter = useEmitter()

const isSavingOwnerAddDialogVisible = ref(false)
const owners = ref([]);
const savingOwner = ref([]);
const contractCardNotification = ref([]);
const deleteDialog = ref(false)
const deleteDialogSubTitle = ref('Are you sure you want to delete this saving owner?')
const selectedSavingOwner = ref();
const errors = ref({}) // fields error receive from api

const props = defineProps({
  savingId: {
    type: Number,
    required: true,
  },
});

// const emit = defineEmits([
//   'response',
// ])

const openSavingOwnerAddDialog = () => {
  scrollTop()
  .then(() => {
    isSavingOwnerAddDialogVisible.value = true
  });
}

// // initial function
onMounted(() => {
  getSavingsOwner();
})

const getSavingsOwner = () => {
  const params = {
    saving_id: props.savingId
  }
  savingStore.fetchSavingOwners(params)
  .then(response => {
    const { savingOwnerList, ownerList } = response.data;
    savingOwner.value = savingOwnerList;
    owners.value = ownerList.filter(item => !savingOwnerList.map(it => it.user_id).includes(item.id));
  })
  .catch(error => {
    console.error(error)
  })
  .finally(() =>{});
}

const deleteSavingOwner = (ownerId) => {
  const deleteData = { id: ownerId }

  savingStore.deleteOwner(deleteData)
  .then(response => {
    const {message} = response.data;
    snackBarStore.message = message;
    snackBarStore.type = 'success';
  })
  .catch(error => {
    const { message, errors: errorData } = error.response.data

    errors.value = errorData
    snackBarStore.error = errorData;
    snackBarStore.message = message;
    snackBarStore.type = 'error';

  })
  .finally(() => {
    snackBarStore.snackbar = true;
    emitter.emit("refreshSavingsChangeLogs"); // refresh the log tab
    // refresh the contract owner
    getSavingsOwner();
  });
}

// add saving owner response
const addNewSavingOwnerResponse = () => {
  // refresh the savings owner
  // emit('response');
  getSavingsOwner();
  emitter.emit("refreshSavingsChangeLogs"); // refresh the log tab
}


const confirmDeleteOwner = () => {
  deleteSavingOwner(selectedSavingOwner.value)
}


const cancelDeletecontractOwner = () => {

}

const deleteAssignedOwner = (owner) => {
  selectedSavingOwner.value = owner;
  deleteDialog.value = true;
};

</script>

<template>
  <VRow>
    <!-- 👉 contract Owners  -->
    <VCol cols="12">
      <VCard :title="$t('Savings Owners')">
        <template #append>
          <VBtn
            v-if="$can('edit', 'savings')"
            prepend-icon="tabler-plus"
            @click="openSavingOwnerAddDialog"
          >
          {{ $t('Add Savings Owner') }}
          </VBtn>
        </template>
        <VCardText>
          {{ $t('Add yourself or other users as a saving owner. Saving owners will receive notifications related to this saving.') }}
        </VCardText>
        <VDivider />
        <!-- style="max-block-size: 28.75rem;" -->
        <!-- :options="{ wheelPropagation: false }" -->
        <PerfectScrollbar >
        <VCardText class="d-flex flex-column gap-y-4">

          <span class="d-flex justify-center flex-wrap mt-3" v-if="savingOwner.length == 0">{{ $t('No Record Found') }}</span>

          <VCard
            v-for="owner in savingOwner"
            :key="owner.user.name"
            border
            flat
          >
            <VCardText class="d-flex flex-sm-row flex-column pa-4">
              <div class="text-no-wrap" v-if="owner.user.status == 1 || owner.user.status == 0">
                
                <VAvatar
                  rounded
                  :size="50"
                  :color="!owner.user.user_image?.file_path ? 'primary' : undefined"
                  :variant="!owner.user.user_image?.file_path ? 'tonal' : undefined"
                >
                  <VImg
                    v-if="owner.user.user_image?.file_path"
                    :src="owner.user.user_image?.file_path"
                  />
                  <span
                    v-else
                    class="text-1xl font-weight-medium"
                  >
                    {{ avatarText(owner.user.name) }}
                  </span>
                </VAvatar>
                
                <p class="text-base my-3 mx-1 text-capitalize">
                  {{ owner.user.name }}
                </p>
              </div>

              <VSpacer />

              <div class="d-flex flex-column text-sm-end gap-2">
                <div class="order-sm-0 order-1">
                  <VBtn
                    v-if="$can('edit', 'savings')"
                    color="error"
                    variant="tonal"
                    @click="deleteAssignedOwner(owner.id)"
                  >
                    {{ $t('Delete') }}
                  </VBtn>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCardText>
      </PerfectScrollbar>
      </VCard>
    </VCol>
  </VRow>
  <!-- 👉 Add New Sub Category -->
  <AddSavingsOwnerDialog
    v-model:isDialogVisible="isSavingOwnerAddDialogVisible"
    :owners="owners"
    :saving-id="props.savingId"
    @response="addNewSavingOwnerResponse"
  />

  <!-- 👉 confirmation dialog for delete Sub Category-->
  <SimpleConfirmDialog
    v-model:isDialogVisible="deleteDialog"
    @click-ok="confirmDeleteOwner"
    @click-cancel="cancelDeletecontractOwner"
    title="Are you sure you want to delete?"
    :sub-title="deleteDialogSubTitle"
    ok-btn-text="Yes"
  />

</template>


<style lang="scss" scoped>

</style>
