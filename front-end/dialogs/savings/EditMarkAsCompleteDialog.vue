<script setup>
import { useSupplierListStore } from "@/router/stores/useSupplierListStore";
import { useSnackBarStore } from "@core/utils/snackBar";
import {
  requiredValidator,
  dateValidator,
  fileTypeValidator,
  decimalValidator
} from "@validators";

import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { formatDate } from "@/plugins/date/dateUtils";

const savingStore = useSavingsStore();
const snackBarStore = useSnackBarStore();
const errors = ref({});

const isFormValid = ref(false);
const refForm = ref();
const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
   milestoneData:{
    type:Object,
    required:true,
  }
  
});
const baselineSpend = ref('');
const realisedCostReduction = ref('');
const realisedCostAvoidance = ref('');

const emit = defineEmits(["response", "update:isDialogVisible"]);
const milestoneData = ref({})

watch(props, () => {
  milestoneData.value = props.milestoneData;
  baselineSpend.value = milestoneData.value.base_line_spend,
  realisedCostReduction.value = milestoneData.value.project_cost_reduction,
  realisedCostAvoidance.value = milestoneData.value.project_cost_avoidance
})

const totalRealisedSaving = computed(() => {
  return (+realisedCostReduction.value + +realisedCostAvoidance.value) || 0;
})

const totalRealisedSavingPer = computed(() => {
  return baselineSpend.value > 0 ? ((totalRealisedSaving.value / baselineSpend.value) * 100).toFixed(2) : 0;
});

const onFormSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      const data = {
      id: props.milestoneData.id,
      base_line_spend: baselineSpend.value,
      realised_cost_reduction: realisedCostReduction.value,
      realised_cost_avoidance: realisedCostAvoidance.value,
      };
      savingStore
      .markAsComplete(data)
      .then((response) => {
          snackBarStore.message = response.data.message;
          snackBarStore.type = 'success';
          emit('response');
          onFormReset();
          errors.value = {} // reset the error 

      })
      .catch((error) => {
          const errorData = error.response.data.errors;
          errors.value = errorData
          snackBarStore.error = errorData;
          snackBarStore.message = error.response.data.message
          snackBarStore.type = 'error';
      })
      .finally(() => {
          snackBarStore.snackbar = true;
      });
    }
  });
};

const onFormReset = () => {
  emit("update:isDialogVisible", false);
  nextTick(() => {
    refForm.value?.resetValidation()
  })
};

const dialogModelValueUpdate = (val) => {
  emit("update:isDialogVisible", val);
  nextTick(() => {
    refForm.value?.resetValidation()
  })
};
</script>

<template>

  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 677"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
    persistent
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-8 pa-5">
      <VCardItem class="text-center">
        <VCardTitle class="text-h5 mb-3">
          {{ $t('Mark Milestone As Complete') }}
        </VCardTitle>
      </VCardItem>

      <VCardText class="d-flex flex-wrap py-4 gap-4">
        <!-- 👉 Form -->
        <VForm
          class="mt-6"
          ref="refForm"
          v-model="isFormValid"
          @submit.prevent="onFormSubmit"
        >
          <VRow>
            <VCol cols="12" >
              <AppTextField
                v-model="realisedCostReduction"
                  :rules="[decimalValidator]"
                  label="Realised Cost Reduction"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="realisedCostAvoidance"
                :rules="[decimalValidator]"
                label="Realised Cost Avoidance"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="totalRealisedSaving"
                readonly
                disabled
                label="Total Realised Savings"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="baselineSpend"
                readonly
                disabled
                label="Baseline Spend"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="totalRealisedSavingPer"
                readonly
                disabled
                label="Total Realised Savings %"
              />
            </VCol>

            <!-- 👉 Submit and Cancel -->
            <VCol cols="12" class="d-flex flex-wrap justify-center gap-4">
              <VBtn type="submit">
                {{ $t('Confirm Milestone Completion') }}
              </VBtn>

              <VBtn
                variant="tonal"
                color="secondary"
                @click="onFormReset"
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
