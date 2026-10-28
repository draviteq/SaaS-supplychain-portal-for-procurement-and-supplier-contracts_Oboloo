<script setup>
import { useSupplierListStore } from "@/router/stores/useSupplierListStore";
import { useSnackBarStore } from "@core/utils/snackBar";
import {
  requiredValidator,
  dateValidator,
  fileTypeValidator,
  minLengthValidator,
  maxUploadSize,
  integerValidator
} from "@validators";

import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { formatDate } from "@/plugins/date/dateUtils";

const savingStore = useSavingsStore();
const snackBarStore = useSnackBarStore();
const errors = ref({});
const uploadLoader = ref(false);
const isSaveButtonClickable = ref(true);
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
const file = ref(null);
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
      isSaveButtonClickable.value = false;

      const data = {
        saving_id:props.savingData.id,
        title:title.value,
        description:description.value,
        file: file.value[0],
        custom_fields: customFieldList.value,
      };

      uploadLoader.value = true;

      savingStore
      .addAttachment(data)
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
        isSaveButtonClickable.value = true;
        uploadLoader.value = false;
      });
    }
  });
};

const onFormReset = () => {
  emit("update:isDialogVisible", false);
  nextTick(() => {
    refForm.value?.reset()
    refForm.value?.resetValidation()
  })
};

const dialogModelValueUpdate = (val) => {
  emit("update:isDialogVisible", val);
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
    @update:model-value="val => dialogModelValueUpdate"
    persistent
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-8 pa-5">
      <VCardItem class="text-center">
        <VCardTitle class="text-h5 mb-3">
          {{ $t('Add Attachment') }}
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
                :error-messages="errors?.title"
                label="Document Title"
                class="required"
              />
            </VCol>
             <VCol cols="12">
              <AppTextarea
                v-model="description"
                auto-grow
                :label="$t('Description/Notes')"
              />
            </VCol>
             <VCol cols="12">
                <VFileInput
                  v-model="file"
                  :rules="[
                    requiredValidator,
                    maxUploadSize,
                    fileTypeValidator(
                      file,
                      'png',
                      'jpg',
                      'jpeg',
                      'pdf',
                      'xls',
                      'xlsx',
                      'txt',
                      'doc',
                      'csv',
                      'ppt',
                      'pptx',
                      'docx',
                      'zip',
                    ),
                  ]"
                  :error-messages="errors?.file"
                  :label="$t('Select Document')"
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


            <!-- 👉 Submit and Cancel -->
            <VCol cols="12" class="d-flex flex-wrap justify-center gap-4">
              <VBtn type="submit" :disabled="uploadLoader" :loading="uploadLoader" > 
                {{ $t('Save Change') }} 
              </VBtn>

              <VBtn color="secondary" variant="tonal" @click="onFormReset" :disabled="uploadLoader">
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
