<script setup>
import { useSavingsStore } from '@/router/stores/useSavingsStore'
import { requiredValidator, floatIntValidator,decimalValidator,minLengthValidator } from "@validators";

const savingsStore = useSavingsStore()
const refForm = ref()
const isFormValid = ref(false)

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  item: {
    type: Object,
    required: true,
  },
})
const emit = defineEmits([
  'update:isDialogVisible',
  'response',
])
const editedItem = ref(structuredClone(toRaw(props.item)))

watch(props, () => {
  editedItem.value = structuredClone(toRaw(props.item));
})

const dialogModelValueUpdate = (val) => {
  emit("update:isDialogVisible", val);
  nextTick(() => {
    refForm.value?.reset()
    refForm.value?.resetValidation()
  })
};

const onSubmit = () => {
  refForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid) {
      emit('update:isDialogVisible', false)
      emit('response', editedItem.value);
    }
  });
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
      <VCardItem>
        <VCardTitle class="text-h4 text-center">
          {{ $t('Edit Milestone')+' :' }} {{ editedItem?.number }}
        </VCardTitle>
      </VCardItem>

      <VCardText class="d-flex flex-wrap py-4 gap-4">
        <!-- 👉 Form -->
        <VForm
          ref="refForm"
          v-model="isFormValid"
          class="mt-4"
          @submit.prevent="onSubmit"
        >
          <VRow>
           <!-- Milestone Name -->
            <VCol
              cols="12"
            >
              <AppTextField 
                v-model="editedItem.name" 
                :rules="[requiredValidator,minLengthValidator(editedItem.name,100)]"
                label="Name"
                class="required"
              />
            </VCol>

            <!-- Start Date -->
            <VCol
              cols="12"
            >
              <AppDateTimePicker  
                v-model="editedItem.start_date" 
                :placeholder="$t('Start Date')"
                :label="$t('Start Date')"
                class="app-date-time-picker required"
                :required="true"
                :rules="[requiredValidator]"
              />
            </VCol>

            <!-- End Date -->
            <VCol
              cols="12"
            >
              <AppDateTimePicker  
                v-model="editedItem.end_date"
                :placeholder="$t('End Date')"
                :label="$t('End Date')"
                class="app-date-time-picker required"
                :required="true"
                :rules="[requiredValidator]"
                :min-date="editedItem.start_date"
              />
            </VCol>

            <!-- Baseline Spend -->
            <VCol
              cols="12"
            >
              <AppTextField 
                v-model="editedItem.base_line_spend" 
                :rules="[decimalValidator]"
                :prefix="savingsStore.currencyIcon"
                label="Total Baseline Spend"
              />
            </VCol>
            
            <!-- Cost Reduction -->
            <VCol
              cols="12"
            >
              <AppTextField 
                v-model="editedItem.project_cost_reduction" 
                :rules="[decimalValidator]"
                :prefix="savingsStore.currencyIcon"
                label="Cost Reduction"
              />
            </VCol>

            <!-- Baseline Spend -->
            <VCol
              cols="12"
            >
              <AppTextField 
                v-model="editedItem.project_cost_avoidance" 
                :rules="[decimalValidator]"
                :prefix="savingsStore.currencyIcon"
                label="Cost Avoidance"
              />
            </VCol>

            <!-- 👉 Submit and Cancel button -->
            <VCol
              cols="12"
              class="d-flex gap-4"
            > 
              <VSpacer />

              <VBtn
                color="error"
                variant="outlined"
                @click="dialogModelValueUpdate(false)"
              >
                {{ $t('Cancel') }}
              </VBtn>

              <VBtn
                color="success"
                variant="elevated"
                type="submit"
              >
                {{ $t('Save') }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
<style lang="scss" scoped>

</style>
