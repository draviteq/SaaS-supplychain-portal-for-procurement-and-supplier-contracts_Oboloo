<script setup>
import { useSupplierListStore } from "@/router/stores/useSupplierListStore";
import { useSnackBarStore } from "@core/utils/snackBar";
import {
  requiredValidator,
  dateValidator,
  fileTypeValidator,
  decimalValidator,
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
  savingData:{
    type:Object,
    required:true,
  },
  customFields: {
    type: Array,
    required: true,
  },
  
});
const description = ref('');
const title = ref('');
const baselineSpend = ref('');
const projectedCostReduction = ref('');
const milestoneNotes = ref('');
const projectedCostAvoidance = ref('');
const milestonesStartDate = ref(null);
const milestonesEndDate = ref(null);
const route = useRoute()
const emit = defineEmits(["response", "update:isDialogVisible"]);

const customFieldList = ref(structuredClone(toRaw(props.customFields)))

watch(props, () => {
  customFieldList.value = structuredClone(toRaw(props.customFields));

  customFieldList.value.forEach(item => {
    if(item.field_type == 4)
      item.formatted_value = item.value == 1 ? true : false;
  })
})

const onFormSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      const data = {
        saving_id:props.savingData.id,
        name:title.value,
        start_date:milestonesStartDate.value,
        end_date:milestonesEndDate.value,
        base_line_spend:baselineSpend.value,
        project_cost_reduction:projectedCostReduction.value,
        project_cost_avoidance:projectedCostAvoidance.value,
        description:milestoneNotes.value,
        custom_fields: customFieldList.value,
      };
      
      savingStore
      .addMilestoneTab(data)
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
    :width="$vuetify.display.smAndDown ? 'auto' : 677"
    :model-value="props.isDialogVisible"
    @update:model-value="val => dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-8 pa-5">
      <VCardItem class="text-center">
        <VCardTitle class="text-h5 mb-3">
          {{ $t('Add Saving Milestone') }}
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
            <VCol cols="12">
              <AppTextField
                 v-model="title"
                :rules="[requiredValidator,minLengthValidator(title,100)]"
                :error-messages="errors?.name"
                label="Milestone Title"
                class="required"
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12" md="6" sm="6">
              <AppDateTimePicker  
                v-model="milestonesStartDate"
                :placeholder="$t('Start Date')"
                :label="$t('Milestone Start Date')"
                class="app-date-time-picker required"
                :required="true"
                :rules="[requiredValidator]"
                :error-messages="errors?.start_date"
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
                :error-messages="errors?.end_date"
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
                  :prefix="props.savingData.currencySymbol"
                  label="Baseline Spend"
                />
            </VCol>

            <VCol cols="12" >
              <AppTextField
                  v-model="projectedCostReduction"
                  :rules="[decimalValidator]"
                  type="number"
                  :prefix="props.savingData.currencySymbol"
                  label="Projected Cost Reduction"
                />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="projectedCostAvoidance"
                :rules="[decimalValidator]"
                type="number"
                :prefix="props.savingData.currencySymbol"
                label="Projected Cost Avoidance"
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
            <VCol cols="12" v-for="(field, index) in customFieldList" :key="index">
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

            <!-- 👉 Submit and Cancel -->
            <VCol cols="12" class="d-flex flex-wrap justify-center gap-4">
              <VBtn type="submit"> {{ $t('Add Saving Milestone') }} </VBtn>

              <VBtn color="secondary" variant="tonal" @click="onFormReset">
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
