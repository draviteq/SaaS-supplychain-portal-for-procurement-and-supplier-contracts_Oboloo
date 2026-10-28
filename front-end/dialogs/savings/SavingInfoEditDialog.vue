<script setup>
import { useSnackBarStore } from "@core/utils/snackBar";
import {
  requiredValidator,
  minLengthValidator,
  dateValidator,
  integerValidator,
} from "@validators";

import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { formatDate } from "@/plugins/date/dateUtils";
import { RelationshipType } from '@core/enums'

const savingStore = useSavingsStore();
const snackBarStore = useSnackBarStore();
const errors = ref({});
const route = useRoute()

const isFormValid = ref(false);
const refForm = ref();
const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  savingData: {
    type: Object,
    required: true,
  },
  customFields: {
    type: Array,
    required: true,
  },
});

const savingData = ref(structuredClone(toRaw(props.savingData)));
const customFieldList = ref(structuredClone(toRaw(props.customFields)))
const selectedSavingType = ref("");
const allSavingType = ref([]);
const selectedCurrency = ref("");
const selectedCategory = ref("");
const selectedSupplier = ref("");
const selectedContract = ref("");
const selectedStatus = ref("");
const selectedSubCategory = ref("");
const selectedUser = ref("");
const allCurrency = ref([]);
const allCategory = ref([]);
const allSubCategory = ref([]);
const allUser = ref([]);
const dep = ref([]);
const loc = ref([]);
const locationList = ref([]);
const departmentList = ref([]);
const contractList = ref([]);
const supplierList = ref([]);
const departmentLocation = ref([]);
const populatedLocationList = ref([]);
const supplierContracts = ref([]);
const populatedDepartmentList = ref([]);
const selectedLocation = ref(null);
const selectedDepartment = ref(null);
const allContract = ref([]);
const allSupplier = ref([]);
const populateSubCategories = ref([]);
const populatedContractList = ref([]);
const populatedSupplierList = ref([]);

const allStatus =[
  { id: 3, name:"Cancelled" },
  { id: 2, name:"Completed" },
  { id: 1, name:"In Progress" },
  { id: 0, name:"Not Started" },
]
const formattedEndDate =ref("")
const formattedCreatedDate =ref("")
const selectedRelationship = ref(null);
const selectedParentChild = ref(null);
const selectedParentChildName = ref(null);
const relationshipTypes = ref([
  { title: 'Parent', id: RelationshipType.PARENT },
  { title: 'Child', id: RelationshipType.CHILD },
]);
const validSuppliers = ref([]);

onMounted(() => {
  fetchParentChildList();
});

watch(props, () => {
  savingData.value = structuredClone(toRaw(props.savingData));
  customFieldList.value = structuredClone(toRaw(props.customFields));

  customFieldList.value.forEach(item => {
    if(item.field_type == 4)
      item.formatted_value = item.value == 1 ? true : false;
  })

  selectedSavingType.value = savingData.value.saving_types_id || null;
  selectedCurrency.value = savingData.value.currency_id || null;
  formattedEndDate.value = formatDate(savingData.value.end_date);
  formattedCreatedDate.value = formatDate(savingData.value.created_at);
  allCurrency.value = savingData.value.allFilter.currency;
  allSavingType.value = savingData.value.allFilter.savingType;
  selectedCategory.value = savingData.value.categoryId;
  selectedStatus.value = savingData.value.status;
  selectedSubCategory.value = savingData.value.sub_category_id;
  selectedSupplier.value = savingData.value.supplier_id;
  selectedContract.value = savingData.value.contractId;
  allCategory.value = savingData.value.allFilter.category;
  allSubCategory.value = savingData.value.allFilter.subcategory;
  allSupplier.value = savingData.value.allFilter.suppliers;
  allContract.value = savingData.value.allFilter.contracts;
  populateSubCategories.value = allSubCategory.value.filter(item => item.code == selectedCategory.value)
   //department
  departmentList.value = savingData.value.allFilter.departments;
  departmentLocation.value = savingData.value.allFilter.departmentsLocations;
  populatedDepartmentList.value = departmentList.value;
    //location
  locationList.value = savingData.value.allFilter.locations;
  populatedLocationList.value = locationList.value;
  selectedUser.value = savingData.value.userId || null;

  // dep.value = [...new Set(savingData.value.departmentIds)];
  selectedDepartment.value = savingData.value.departmentIds;
  // loc.value = [...new Set(savingData.value.locationIds)];
  selectedLocation.value = savingData.value.locationIds;

  //Associated Contract
  
    //Contract
  contractList.value = savingData.value.allFilter.contracts;
  supplierContracts.value = savingData.value.allFilter.supplierContracts;
  populatedContractList.value = contractList.value;
    //Supplier
  supplierList.value = savingData.value.allFilter.suppliers;
  populatedSupplierList.value = supplierList.value;

  populateContracts(selectedSupplier.value);
  populateDepartments(selectedLocation.value);
  populateLocation(selectedDepartment.value);

  selectedRelationship.value = savingData.value?.additionalData?.parent_child?.relation_type || null;
  selectedParentChild.value = savingData.value?.additionalData?.parent_child?.parent_child_id || null;
  selectedParentChildName.value = savingData.value?.additionalData?.parent_child?.saving_record?.title || null;

});

//==============================End Watcher for Data===================================

// populated contract for selected supplier
const populateContracts = (supplier) => {

    if (selectedSupplier?.value != savingData?.value?.supplier_id) 
        selectedContract.value = null;
      
    if(supplier){
      const supplierContract = supplierContracts.value.filter(item => item.supplier_id == supplier).map(ele => ele.contract_id);
      populatedContractList.value = contractList.value.filter(item => supplierContract.includes(item.id));
    }else{
      selectedContract.value = null;
    }
}
const populateSubCategory = (subCategory) => {
  selectedSubCategory.value = null;
  populateSubCategories.value = allSubCategory.value.filter(item => item.code == subCategory) 
}

// populated location for selected department
const populateLocation = (department) => {
  if(department) {
    const locations = departmentLocation.value.filter(item => department == item.department_id).map(item => item.location_id);
    const updatedList = locationList.value.filter(item => (locations.includes(item.id) ));
    populatedLocationList.value = updatedList;
  }else{
    selectedLocation.value = null;
    populatedLocationList.value = locationList.value;
    populatedDepartmentList.value = departmentList.value;

  }
}

// populated department for selected location
const populateDepartments = (location) => {
  if(location) {
    const departments = departmentLocation.value.filter(item => location == item.location_id).map(item => item.department_id);
    const updatedList = departmentList.value.filter(item => (departments.includes(item.id)));
    populatedDepartmentList.value = updatedList;
  }else{
    selectedDepartment.value = null;
    populatedLocationList.value = locationList.value;
    populatedDepartmentList.value = departmentList.value;
  }
}

const emit = defineEmits(["response", "update:isDialogVisible"]);
const onFormSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      const data = {
        id: savingData.value.id,
        name: savingData.value?.savingName,
        description: savingData.value.description,
        status:selectedStatus.value,
        currency_id: selectedCurrency.value,
        category_id: selectedCategory.value,
        sub_category_id: selectedSubCategory.value,
        location: selectedLocation.value,
        department: selectedDepartment.value,
        supplier_id: selectedSupplier.value,
        contract_id: selectedContract.value,
        saving_type: selectedSavingType?.value,
        relation_type: +selectedRelationship.value,
        parent_child_id: +selectedParentChild.value,
        custom_fields: customFieldList.value,
      };
      updateSavings(data);
    }
  });
};
const updateSavings = (data) => {
  savingStore
    .updateSavingsOneData(data)
    .then((response) => {
      snackBarStore.message = response.data.message;
      snackBarStore.type = "success";
      errors.value = {};
      emit("update:isDialogVisible", false);
      emit("response");
      nextTick(() => {
        refForm.value?.reset();
        refForm.value?.resetValidation();
      });
    })
    .catch((error) => {
      const { message, errors: errorData } = error.response.data;
      errors.value = errorData;
      snackBarStore.message = message;
      snackBarStore.type = "error";
      snackBarStore.error = errorData;
    })
    .finally(() => {
      snackBarStore.snackbar = true;
    });
};
const onFormReset = () => {
  emit("update:isDialogVisible", false);
};

const dialogModelValueUpdate = (val) => {
  emit("update:isDialogVisible", val);
};


const fetchParentChildList = () => {
  const params = {
    id: route.params.id,
  }
  savingStore.fetchParentChildList(params)
  .then(response => {
    validSuppliers.value = response.data;
  }).catch(err => {
    console.error(err);
  })
}

</script>

<template>

  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
    persistent
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-8 pa-5">
      <VCardItem class="text-center">
        <VCardTitle class="text-h5 mb-3">
          {{ $t('Edit Saving Information') }}
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
                v-model="savingData.savingName"
                :error-messages="errors?.name"
                :rules="[requiredValidator,minLengthValidator(savingData.savingName,100)]"
                label="Saving Title"
                class="required"
              />
            </VCol>

            <VCol cols="12">
                <AppSelect
                  v-model="selectedStatus"
                  :items="allStatus"
                  item-title="name"
                  item-value="id"
                  clearable
                  clear-icon="mdi-close"
                  class="small required"
                  :error-messages="errors?.status"
                  :rules="[requiredValidator]"
                  label="Status"
                />
            </VCol>

            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedCategory"
                :items="allCategory"
                item-title="value"
                item-value="id"
                clearable
                :error-messages="errors?.category_id"
                :rules="[requiredValidator]"
                clear-icon="mdi-close"
                class="small required"
                @update:model-value="populateSubCategory"
                label="Category"
              />
            </VCol>

            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedSubCategory"
                :items="populateSubCategories"
                item-title="value"
                item-value="id"
                clearable
                clear-icon="mdi-close"
                class="small"
                label="Sub-Category"
              />
            </VCol>

            <!-- 👉 location -->
            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedLocation"
                :items="populatedLocationList"
                item-title="location_name"
                item-value="id"
                clearable
                :error-messages="errors?.location"
                :rules="[requiredValidator]"
                clear-icon="mdi-close"
                class="small required"
                @update:model-value="populateDepartments"
                label="Select Location"
              />
            </VCol>

            <!-- 👉 department -->
            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedDepartment"
                :items="populatedDepartmentList"
                item-title="department_name"
                item-value="id"
                :error-messages="errors?.department"
                clearable
                :rules="[requiredValidator]"
                clear-icon="mdi-close"
                class="small required"
                @update:model-value="populateLocation"
                label="Select Department"
              />
            </VCol>

            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedSupplier"
                :items="populatedSupplierList"
                item-title="name"
                item-value="id"
                clearable
                clear-icon="mdi-close"
                class="small"
                closable-chips
                @update:model-value="populateContracts"
                label="Select Supplier"
              />
            </VCol>

            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedContract"
                :items="populatedContractList"
                item-title="title"
                item-value="id"
                clearable
                clear-icon="mdi-close"
                class="small"
                closable-chips
                label="Associated Contract"
              />
            </VCol>
            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedSavingType"
                :items="allSavingType"
                item-title="name"
                item-value="id"
                clearable
                clear-icon="mdi-close"
                class="small"
                label="Saving Type"
              />
            </VCol>
            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedCurrency"
                :items="allCurrency"
                item-title="currency"
                item-value="id"
                :rules="[requiredValidator]"
                clear-icon="mdi-close"
                :error-messages="errors?.currency_id"
                class="small required"
                readonly
                disabled
                label="Currency"
              />
            </VCol>
            
            <VCol cols="12">
              <AppTextField
                v-model="savingData.total_base_line_spend"
                :prefix="savingData.currencySymbol"
                readonly 
                disabled
                label="Total Baseline Spend"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="savingData.total_project_saving"
                :prefix="savingData.currencySymbol"
                readonly 
                disabled
                label="Total Projected Saving"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="savingData.total_project_saving_perc"
                readonly 
                disabled
                label="Total Projected Savings %"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="savingData.total_realised_saving_base_line"
                :prefix="savingData.currencySymbol"
                readonly 
                disabled
                label="Total Baseline Spend Against Realised Savings"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="savingData.total_realised_saving"
                :prefix="savingData.currencySymbol"
                readonly 
                disabled
                label="Total Realised Savings"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="savingData.total_realised_saving_perc"
                readonly 
                disabled
                label="Total Realised Savings %"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="formattedEndDate"
                readonly 
                disabled
                label="Savings Due Date"
              />
            </VCol>

            <!-- 👉 Parent/Child Relationship -->
            <VCol cols="12">
              <AppSelect
                v-model="selectedRelationship"
                :items="relationshipTypes"
                item-title="title"
                item-value="id"
                clearable
                :label="$t('Saving Parent/Child Relationship')"
                clear-icon="mdi-close"
                class="small"
              />
            </VCol>

            <!-- 👉 Parent/Child of --> 
            <VCol cols="12">
              <AppAutocomplete
                v-model="selectedParentChild"
                :items="validSuppliers"
                item-title="name"
                item-value="id"
                clearable
                clear-icon="mdi-close"
                class="small"
                label="Parent/Child of Saving"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="formattedCreatedDate"
                readonly 
                disabled
                label="Created On"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="savingData.name"
                readonly 
                disabled
                label="Created By"
              />
            </VCol>
           
            <VCol cols="12">
              <VTextarea
                v-model="savingData.description"
                :error-messages="errors?.description"
                :rules="[requiredValidator]"
                label="Saving Description/Notes"
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
              <VBtn type="submit"> {{ $t('Save Change') }}</VBtn>

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
