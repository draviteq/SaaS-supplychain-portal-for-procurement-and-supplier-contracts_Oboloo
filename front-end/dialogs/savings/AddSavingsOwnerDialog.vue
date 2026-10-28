<script setup>

import {
  requiredValidator,
  minLengthValidator,
  notNumericValidator,
} from '@validators'
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { useSnackBarStore } from "@core/utils/snackBar";

const savingStore = useSavingsStore();
const snackBarStore = useSnackBarStore();

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  owners: {
    type: Array,
    required: true,
  },
  savingId: {
    type: Number,
    required: true,
  },
})

const isFormValid = ref(false)
const refForm = ref()

const emit = defineEmits([
  'response',
  'update:isDialogVisible',
])

const selectedcontractOwner = ref(null);
const errors = ref({}) // fields error receive from api

const onFormSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {

      const postData = {
        user_id: selectedcontractOwner.value,
        saving_id: props.savingId
      }
      savingStore.addOwner(postData)
      .then(response => {
        const {message} = response.data;

        snackBarStore.message = message;
        snackBarStore.type = 'success';
        emit('update:isDialogVisible', false)
        emit('response')
        nextTick(() => {
          refForm.value?.reset()
          refForm.value?.resetValidation()
        })
        errors.value = {}
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
      });
    }
  })
}


const dialogModelValueUpdate = val => {
  errors.value = {}
  emit('update:isDialogVisible', false)
  nextTick(() => {
    refForm.value?.reset()
    refForm.value?.resetValidation()
  })
};


</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 677"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-8 pa-5">
      <VCardItem class="text-center">
        <VCardTitle class="text-h5 mb-3">
          {{ $t('Add New Saving Owner') }}
        </VCardTitle>
      </VCardItem>

      <VCardText>
        {{ $t('Add yourself or other users as a saving owner. Savings owners will receive notifications related to this saving.') }}
      </VCardText>

      <VCardText class="py-4 gap-4">
        <!-- 👉 Form -->
        <VForm
          class="mt-6"
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onFormSubmit"
        >
          <VRow>

            <!-- 👉 categories -->
            <VCol
              cols="12"
            >
              <AppAutocomplete
                v-model="selectedcontractOwner"
                :items="props.owners"
                item-title="name"
                item-value="id"
                clearable
                clear-icon="mdi-close"
                class="small required"
                :rules="[requiredValidator]"
                :error-messages="errors.owner"
                label="Select Saving Owner"
              />
            </VCol>

            <!-- 👉 Submit and Cancel -->
            <VCol
              cols="12"
              class="d-flex flex-wrap justify-center gap-4"
            >
              <VBtn type="submit">
                {{ $t('Save') }}
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
                @click="dialogModelValueUpdate(false)"
              >
                {{ $t('Cancel') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
  .required-label {
    color: red;
  }
</style>
