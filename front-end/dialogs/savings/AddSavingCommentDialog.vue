
<script setup>
import { VForm } from 'vuetify/components/VForm'
import { requiredValidator, integerValidator, minLengthValidator } from '@validators'
import { useSnackBarStore } from "@core/utils/snackBar";
import { useSavingsStore } from "@/router/stores/useSavingsStore";

const savingAddComment = useSavingsStore();
const snackBarStore = useSnackBarStore();
const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  savingId: {
    type: Number,
    required: true,
  },
  customFields: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'commentData',
])

const customFieldList = ref(structuredClone(toRaw(props.customFields)))

watch(props, () => {
  customFieldList.value = structuredClone(toRaw(props.customFields));

  customFieldList.value.forEach(item => {
    if(item.field_type == 4)
      item.formatted_value = item.value == 1 ? true : false;
  })
})

const isFormValid = ref(false)
const refForm = ref()
const comment = ref('')
const errors = ref({})
const onSubmit = () => {

  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      savingAddComment
      .addSavingComment({
          comment: comment.value,
          saving_id: props.savingId,
          custom_fields: customFieldList.value,
      })
      .then((response) => {
          refForm.value.reset();
          props.isDialogVisible = false; // Close the dialog
          snackBarStore.message = response.data.message;
          snackBarStore.type = 'success';
          emit('update:isDialogVisible', false);
          emit('commentData');
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
}

const onReset = () => {
  errors.value = {} // reset the error 
  emit('update:isDialogVisible', false);
};

</script>


<template>
  <VDialog
    :width="$vuetify.display.mdAndDown ? 'auto' : 650"
    :model-value="props.isDialogVisible"
    @update:model-value="val => onReset"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="onReset" />

    <VCard class="pa-sm-8 pa-5">
      <!-- 👉 Title -->
      <VCardItem class="text-center">
        <VCardTitle class="text-h3 mb-3">
          {{ $t('Add Note') }}
        </VCardTitle>
      </VCardItem>

      <VCardText class="pt-6">
        <!-- 👉 Form -->
        <VForm ref="refForm" v-model="isFormValid" @submit.prevent="onSubmit">
          <VRow>
            <VCol cols="12">
              <AppTextarea
                v-model="comment"
                :rules="[requiredValidator]"
                :error-messages="errors.comment"
                auto-grow
                :label="$t('Note')"
                class="required"
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

            <!-- 👉 Actions button -->
            <VCol cols="12" class="text-center">
              <VBtn type="submit" class="me-3">
                {{ $t('Save') }}
              </VBtn>
              <VBtn variant="tonal" color="secondary" @click="onReset">
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
  .app-autocomplete {
    position: relative;
  }
</style>
