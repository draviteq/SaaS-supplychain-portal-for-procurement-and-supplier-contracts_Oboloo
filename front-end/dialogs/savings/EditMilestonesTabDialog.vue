<script setup>
import { useSupplierListStore } from "@/router/stores/useSupplierListStore";
import { useSnackBarStore } from "@core/utils/snackBar";
import {
  requiredValidator,
  dateValidator,
  fileTypeValidator,
  decimalValidator,
  nonZeroValidator,
  minLengthValidator,
  integerValidator
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
  },

  currencySymbol: {
    type: String,
    required: false,
  },
  isBaseCurrency: Boolean,
  
});
const description = ref('');
const title = ref('');
const baselineSpend = ref(0);
const projectedCostReduction = ref(0);
const milestoneNotes = ref('');
const projectedCostAvoidance = ref(0);
const currencyRate = ref(1);
const basecurrencyRate = ref(1);
const milestonesStartDate = ref(null);
const milestonesEndDate = ref(null);
const route = useRoute()
const emit = defineEmits(["response", "update:isDialogVisible"]);
const milestoneData = ref(structuredClone(toRaw(props.milestoneData)))
const customFields = ref([]);

watch(props, () => {
  milestoneData.value = structuredClone(toRaw(props.milestoneData));

  customFields.value = milestoneData.value?.custom_fields || [];
  customFields.value.forEach(item => {
    if(item.field_type == 4)
      item.formatted_value = item.value == 1 ? true : false;
  });

  milestonesStartDate.value = milestoneData.value.start_date,
  milestonesEndDate.value = milestoneData.value.end_date,
  baselineSpend.value = milestoneData.value.base_line_spend,
  projectedCostReduction.value = milestoneData.value.project_cost_reduction,
  projectedCostAvoidance.value = milestoneData.value.project_cost_avoidance,
  currencyRate.value = milestoneData.value.currency_rate,
  milestoneNotes.value = milestoneData.value.note
})

const onFormSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      const data = {
        id: props.milestoneData.id,
        start_date: milestonesStartDate.value,
        end_date: milestonesEndDate.value,
        base_line_spend: baselineSpend.value,
        project_cost_reduction: projectedCostReduction.value,
        project_cost_avoidance: projectedCostAvoidance.value,
        currency_rate: currencyRate.value,
        note: milestoneNotes.value,
        custom_fields: customFields.value,
      };
      savingStore
      .editMilestoneTab(data)
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
    milestonesStartDate.value=null
    milestonesEndDate.value=null
    refForm.value?.reset()
    refForm.value?.resetValidation()
  })
};

const dialogModelValueUpdate = (val) => {
  emit("update:isDialogVisible", val);
  nextTick(() => {
    milestonesStartDate.value=null
    milestonesEndDate.value=null
    refForm.value?.reset()
    refForm.value?.resetValidation()
  })
};
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 610 "
    :model-value="props.isDialogVisible"
    @update:model-value="val => dialogModelValueUpdate"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard
      class="pa-sm-8 pa-5"
    >
      <!-- 👉 Title -->
      <VCardItem class="text-center">
        <VCardTitle class="text-h5 mb-3">
          {{ $t('Edit Saving Milestones') }}
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
             <VCol cols="12" md="6" sm="6">
                <AppDateTimePicker  
                  v-model="milestonesStartDate"
                  :placeholder="$t('Start Date')"
                  :label="$t('Milestone Start Date')"
                  class="app-date-time-picker required"
                  :required="true"
                  :rules="[requiredValidator]"
                />
            </VCol>

            <VCol cols="12" md="6" sm="6">
              <AppDateTimePicker  
                v-model="milestonesEndDate"
                :placeholder="$t('End Date')"
                :label="$t('Milestone End Date')"
                class="app-date-time-picker required"
                :required="true"
                :rules="[requiredValidator]"
                :min-date="milestonesStartDate"
              />
            </VCol>
          </VRow>
          
          <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="baselineSpend"
                  :rules="[decimalValidator]"
                  type="number"
                  :prefix="props?.currencySymbol"
                  label="Baseline Spend"
                />
              </VCol>

             <VCol cols="12" >
                <AppTextField
                  v-model="projectedCostReduction"
                  :rules="[decimalValidator]"
                  type="number"
                  :prefix="props?.currencySymbol"
                  label="Projected Cost Reduction" 
                />
             </VCol>
            
             <VCol cols="12">
                <AppTextField
                  v-model="projectedCostAvoidance"
                  :rules="[decimalValidator]"
                  type="number"
                  :prefix="props?.currencySymbol"
                  label="Projected Cost Avoidance"
                />
            </VCol>

            <VCol cols="12" v-if="!props.isBaseCurrency">
              <AppTextField
                v-model="basecurrencyRate"
                :rules="[decimalValidator]"
                type="number"
                disabled
                readonly
                label="Base Currency"
              />
            </VCol>

            <VCol cols="12" v-if="!props.isBaseCurrency">
              <AppTextField
                v-model="currencyRate"
                :rules="[decimalValidator, requiredValidator, nonZeroValidator]"
                type="number"
                min="1"
                label="Exchange Rate Against Base Currency"
              />
            </VCol>

            <VCol cols="12">
                <AppTextarea
                  v-model="milestoneNotes"
                  auto-grow
                  :label="$t('Milestone Notes')"
                />
            </VCol>

            <!-- 👉 custom fields --> 
            <VCol cols="12" v-for="(field, index) in customFields" :key="index">
              <!-- Text Field -->
              <template v-if="field?.field_type == 1">
                <AppTextField
                  v-model="field.formatted_value"
                  :label="field.field_name"
                  :rules="[minLengthValidator(field.formatted_value, 100)]"
                />
              </template>

              <!-- Number Field -->
              <template v-else-if="field?.field_type == 2">
                <AppTextField
                  v-model="field.formatted_value"
                  :rules="[integerValidator]"
                  :label="field.field_name"
                />
              </template>

              <!-- Date Picker -->
              <template v-else-if="field?.field_type == 3">
                <AppDateTimePicker
                  v-model="field.formatted_value"
                  :label="$t(field.field_name)"
                  class="app-date-time-picker"
                />
              </template>

              <!-- Boolean (Checkbox) -->
              <template v-else>
                <VCheckbox
                  v-model="field.formatted_value"
                  :label="$t(field.field_name)"
                />
              </template>
            </VCol>

            <!-- 👉 Submit and Cancel button -->
            <VCol
              cols="12"
              class="d-flex gap-4"
            > 
              <VSpacer />
            
             <VBtn type="submit"> {{ $t('Update Milestone') }} </VBtn>
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
