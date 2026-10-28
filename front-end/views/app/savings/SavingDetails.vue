<script setup>
import { useSavingsStore } from '@/router/stores/useSavingsStore'
import { useSnackBarStore } from "@core/utils/snackBar";
import { requiredValidator, minLengthValidator} from "@validators";
import { watch } from 'vue';
import { useLocationListStore } from "@/router/stores/useLocationListStore";
import { useDepartmentListStore } from '@/router/stores/useDepartmentListStore';
import { scrollTop } from "@/plugins/dashboards/utils";

const departmentListStore = useDepartmentListStore()
const locationListStore = useLocationListStore();
const savingsStore = useSavingsStore()
const snackBarStore = useSnackBarStore();
const refForm = ref()
const isFormValid = ref(false)
const savingTitle = ref("");
const description = ref("");
const selectedCurrency = ref(null);
const selectedCategory = ref(null);
const selectedSubCategory = ref(null);
const selectedLocation = ref(null);
const selectedDepartment = ref(null);
const selectedStatus = ref(null);
const selectedSavingType = ref(null);
const selectedSupplier = ref(null);
const selectedContract = ref(null);
const savingsId = ref(null);

const statusList = ref([]);
const savingsType = ref([]);
const categoryList = ref([]);
const subCategoryList = ref([]);
const populatedSubCategoryList = ref([]);
const populatedLocationList = ref([]);
const populatedDepartmentList = ref([]);
const locationList = ref([]);
const locationDeparmentList = ref([]);
const departmentList = ref([]);
const currencyList = ref([]);
const supplierList = ref([]);
const contractList = ref([]);
const populatedContractList = ref([]);
const SupplierContractList = ref([]);
const loggedInUserId = useLocalStorage('loginUserId')
const reloadTable = ref(false);
const addSavingsLoader = ref(false);
const supplierCreateDialog = ref(false);
const departmentCreateDialog = ref(false);
const locationCreateDialog = ref(false);

const emit = defineEmits([
  'prev',
  'next',
])

const props = defineProps({
  draftSavingData: {
    type: Object,
    required: false,
  },
})

// initial function
onMounted(() => {
  fetchSavingFilters() // fetch the filters options
  fetchCountry();
  fetchLocation();
});

const country = ref([]);

const locations = ref([]);
const locationListData = ref([])

const fetchLocation = () => {
  departmentListStore.fetchLocationForDepartment() 
  .then(response => {
    locationListData.value=response.data.locations

    
  })
}

const fetchCountry = () => {
  locationListStore.fetchCountry().then((response) => {
    country.value = response.data.data;
    const supplier = response.data.data.supplier;
  });
};


watch(props, () => {
  savingsId.value = props.draftSavingData.id || null;
  savingTitle.value = props.draftSavingData.name || null;
  description.value = props.draftSavingData.description || null;
  selectedStatus.value = props.draftSavingData.status || null;
  selectedCategory.value = props.draftSavingData.category_id || null;
  selectedSubCategory.value = props.draftSavingData.sub_category_id || null;
  selectedCurrency.value = props.draftSavingData.currency_id || null;
  selectedLocation.value = props.draftSavingData?.get_contract_location_department[0]?.get_dpartment_location?.location_id || null;
  selectedDepartment.value = props.draftSavingData?.get_contract_location_department[0]?.get_dpartment_location?.department_id || null;
  selectedSupplier.value = props.draftSavingData.supplier_id || null;
  selectedContract.value = props.draftSavingData.contract_id || null;
  selectedSavingType.value = +props.draftSavingData.saving_type || null; 
})
// fetch saving filters
const fetchSavingFilters = () => {
  savingsStore.fetchActiveSavingFilters()
  .then((response) => {
    const { categories, 
            SubCategory, 
            departments, 
            locations, 
            departmentsLocations, 
            status, 
            currency, 
            suppliers, 
            contracts,
            SupplierContract,
            savingType
          } = response.data;

    categoryList.value = categories;
    subCategoryList.value = SubCategory;
    locationList.value = locations;
    locationDeparmentList.value = departmentsLocations;
    departmentList.value = departments;
    statusList.value = status.filter(item => item.value != 4);
    currencyList.value = currency;
    supplierList.value = suppliers;
    contractList.value = contracts;
    SupplierContractList.value = SupplierContract;
    savingsType.value = savingType;
    // populatedContractList.value = contractList.value;
    populatedLocationList.value = locationList.value;
    populatedDepartmentList.value = departmentList.value;

  }).catch((error) => {
    const { message, errors: errorData} = error.response.data
    snackBarStore.error = errorData;
    snackBarStore.message = message;
    snackBarStore.type = 'error';

    snackBarStore.snackbar = true;
  });
}

// populated sub category for selected category
const populateSubCategory = (val) => {
  if(val){
    populatedSubCategoryList.value = subCategoryList.value.filter(item => item.code == val);
  }
  selectedSubCategory.value = null;
}

// populated location for selected department
const populateLocation = (department) => {
  if(department) {
    const locations = locationDeparmentList.value.filter(item => department == item.department_id).map(item => item.location_id);
    if (!locations.includes(selectedLocation.value)) {
        selectedLocation.value = null;
      }
      
    const updatedList = locationList.value.filter(item => (locations.includes(item.id) ));
    populatedLocationList.value = updatedList;
  }else{
    selectedLocation.value = null;
    populatedLocationList.value = locationList.value;
    populatedDepartmentList.value = departmentList.value;

  }
}

// populated department for selected location
const populateDepartment = (location) => {
  if(location) {
    const departments = locationDeparmentList.value.filter(item => location == item.location_id).map(item => item.department_id);
    if (!departments.includes(selectedDepartment.value)) {
        selectedDepartment.value = null;
      }
    const updatedList = departmentList.value.filter(item => (departments.includes(item.id)));
    populatedDepartmentList.value = updatedList;
  }else{
    selectedDepartment.value = null;
    populatedLocationList.value = locationList.value;
    populatedDepartmentList.value = departmentList.value;
  }
}

// populated contract for selected supplier
const populateContracts = (supplier) => {
  if(supplier){
    const supplierContract = SupplierContractList.value.filter(item => item.supplier_id == supplier).map(ele => ele.contract_id);
    populatedContractList.value = contractList.value.filter(item => supplierContract.includes(item.id));
  }else{
    populatedContractList.value = [];
  }
  selectedContract.value = null;
}

const onSubmit = () => {

  refForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid) {
      addSavingsLoader.value = true;

      const savingRecord = {
        id: savingsStore.recentSavingsId || savingsId.value,
        name: savingTitle.value,
        description: description.value,
        status: selectedStatus.value,
        category_id: selectedCategory.value,
        sub_category_id: selectedSubCategory.value,
        currency_id: selectedCurrency.value,
        location: selectedLocation.value,
        department: selectedDepartment.value,
        supplier_id: selectedSupplier.value,
        contract_id: selectedContract.value,
        saving_type: selectedSavingType.value,
      }

      savingsStore.savingsRecord = savingRecord;

      savingsStore.createSavingsRecord(savingRecord)
      .then((response) => {
        snackBarStore.message = response.data.message;
        snackBarStore.type = 'success';
        savingsStore.recentSavingsId = response.data?.saving_id;

        emit('next', 1);
      })
      .catch((error) => {
        const { message, errors: errorData} = error.response.data
        snackBarStore.error = errorData;
        snackBarStore.message = message;
        snackBarStore.type = 'error';
      })
      .finally(() => {
        snackBarStore.snackbar = true;
        addSavingsLoader.value = false;
      });
    }
  });
};

const getCurrencyIcon = (val) => {
  const icon = currencyList.value.filter(item => item.id == val).map(i =>  i.currency_symbol);
  savingsStore.currencyIcon = icon[0];
}


const handleNewSupplierClick = () =>{
  scrollTop()
  .then(() => {
    supplierCreateDialog.value = true;
  });
}

const handleNewLocationClick = () =>{
  // selectedDepartment.value = null;
  // selectedLocation.value = null;
  // populatedLocationList.value = []
  // populatedDepartmentList.value = []
  scrollTop()
  .then(() => {
    locationCreateDialog.value = true;
  });
}

const handleNewDepartmentClick = () =>{
  // selectedDepartment.value = null;
  // selectedLocation.value = null;
  // populatedLocationList.value = []
  // populatedDepartmentList.value = []
  scrollTop()
  .then(() => {
    departmentCreateDialog.value = true;
  });
}

const refreshSupplier = () => {
  fetchSavingFilters();
  fetchCountry();
  fetchLocation();
}
/** filter the list with help of search query with the limit of 25 items */

const supplierSearchQuery = ref('');

const onSupplierSearch = (query) => {
  // if (!query) return;

  supplierSearchQuery.value = query;
}

// Computed property for filtering and limiting items
const filteredSupplierItems = computed(() => {
  const query = supplierSearchQuery.value.trim().toLowerCase();  

  // Filter the list based on the search query
  let filtered = query
    ? supplierList.value.filter((item) =>
        item.name?.toLowerCase().includes(query)
      )
    : supplierList.value;

  if(selectedSupplier.value){
    const selectedItem = supplierList.value.filter(
      (item) => selectedSupplier.value == item.id
    );
  
    if(selectedItem.length > 0){
      filtered = [...new Set([...selectedItem, ...supplierList.value])];
    }
  }

  // Limit to 25 items
  return filtered.slice(0, 25);
});

/** end */

/** filter the list with help of search query with the limit of 25 items */

const contractSearchQuery = ref('');

const onContractSearch = (query) => {
  // if (!query) return;

  contractSearchQuery.value = query;
}

// Computed property for filtering and limiting items
const filteredContractItems = computed(() => {
  const query = contractSearchQuery.value.trim().toLowerCase();  

  // Filter the list based on the search query
  let filtered = query
    ? populatedContractList.value.filter((item) =>
        item.name?.toLowerCase().includes(query)
      )
    : populatedContractList.value;

  if(selectedContract.value){
    const selectedItem = populatedContractList.value.filter(
      (item) => selectedContract.value == item.id
    );
  
    if(selectedItem.length > 0){
      filtered = [...new Set([...selectedItem, ...populatedContractList.value])];
    }
  }

  // Limit to 25 items
  return filtered.slice(0, 25);
});

/** end */
</script>

<template>
  <VCardItem class="text-start">
    <VCardTitle class="text-h5">
      {{ $t('Savings Summary') }}
    </VCardTitle>
  </VCardItem>

  <VDivider />

  <VCardText class="pt-2">
    <!-- 👉 Form -->
    <VForm 
      class="mt-6" 
      ref="refForm"
      v-model="isFormValid"
      @submit.prevent="onSubmit"
      v-if="!reloadTable"
    >
      <VRow>
        <VCol cols="12" md="12">
          <AppTextField 
            v-model="savingTitle" 
            :rules="[requiredValidator,minLengthValidator(savingTitle,100)]"
            label="Saving Title"
            class="required"
          />
        </VCol>

        <VCol cols="12" md="12">
          <AppTextarea
            v-model="description"
            auto-grow
            :rules="[requiredValidator]"
            label="Saving Description/Notes"
            class="required"
          />
        </VCol>

        <VCol cols="12" md="12">
          <AppAutocomplete
            v-model="selectedCurrency"
            :items="currencyList"
            item-title="currency"
            item-value="id"
            :rules="[requiredValidator]"
            @update:model-value="getCurrencyIcon"
            label="Saving Currency"
            class="required"
          />
        </VCol>

        <VCol cols="12" md="6">
          <AppSelect
            v-model="selectedStatus"
            :items="statusList"
            clearable
            clear-icon="mdi-close"
            class="small required"
            :rules="[requiredValidator]"
            label="Saving Status"
          />
        </VCol>

        <VCol cols="12" md="6">
          <AppAutocomplete
            v-model="selectedSavingType"
            :items="savingsType"
            item-value="id"
            item-title="name"
            clearable
            clear-icon="mdi-close"
            class="small"
            label="Savings Type"
          />
        </VCol>

        <VCol cols="12" md="6">
          <AppAutocomplete
            v-model="selectedCategory"
            :items="categoryList"
            item-value="id"
            item-title="value"
            clearable
            clear-icon="mdi-close"
            class="small required"
            :rules="[requiredValidator]"
            @update:model-value="populateSubCategory"
            label="Saving Category"
          />
        </VCol>

        <VCol cols="12" md="6">
          <AppAutocomplete
            v-model="selectedSubCategory"
            :items="populatedSubCategoryList"
            item-value="id"
            item-title="value"
            clearable
            clear-icon="mdi-close"
            class="small"
            label="Saving Sub-Category"
          />
        </VCol> 

        <!-- 👉 Location -->
        <VCol cols="12" md="6">
          <AppAutocomplete
            :items="populatedLocationList"
            v-model="selectedLocation"
            item-title="location_name"
            item-value="id"
            clearable
            clear-icon="mdi-close"
            class="small required no-flex"
            :rules="[requiredValidator]"
            @update:model-value="populateDepartment"
            label="Location"
          >
          <template v-slot:prepend-item>
            <VListItem
              v-if="populatedLocationList.length"
              @click="handleNewLocationClick"
              class="new-location-btn"
              value=""
            >
              <VListItemContent>
                <VListItemTitle>+ {{$t("New Location")}}</VListItemTitle>
              </VListItemContent>
            </VListItem>
          </template>

          <template v-slot:no-data>
            <VListItem
              v-if="!populatedLocationList.length"
              @click="handleNewLocationClick"
              class="new-location-btn"
              value=""
            >
              <VListItemContent>
                <VListItemTitle>+ {{$t("New Location")}}</VListItemTitle>
              </VListItemContent>
            </VListItem>
          </template>
          </AppAutocomplete>
        </VCol> 

        <!-- 👉 Department -->
        <VCol cols="12" md="6">
          <AppAutocomplete
            :items="populatedDepartmentList"
            v-model="selectedDepartment"
            item-title="department_name" 
            item-value="id"
            clearable
            clear-icon="mdi-close"
            class="small required no-flex"
            :rules="[requiredValidator]"
            @update:model-value="populateLocation"
            label="Department"
          >
          <template v-slot:prepend-item>
            <VListItem
              v-if="populatedDepartmentList.length"
              @click="handleNewDepartmentClick"
              class="new-department-btn"
              value=""
            >
              <VListItemContent>
                <VListItemTitle>+ {{$t("New Department")}}</VListItemTitle>
              </VListItemContent>
            </VListItem>
          </template>

          <template v-slot:no-data>
            <VListItem
              v-if="!populatedDepartmentList.length"
              @click="handleNewDepartmentClick"
              class="new-department-btn"
              value=""
            >
              <VListItemContent>
                <VListItemTitle>+ {{$t("New Department")}}</VListItemTitle>
              </VListItemContent>
            </VListItem>
          </template>
        </AppAutocomplete>
        </VCol>

        <!-- 👉 Supplier Name -->
        <VCol cols="12" md="6">
          <AppAutocomplete
            :items="filteredSupplierItems"
            v-model="selectedSupplier"
            item-title="name" 
            item-value="id"
            clearable
            clear-icon="mdi-close"
            class="small"
            @update:model-value="populateContracts"
            label="Supplier Name"
            @update:search="onSupplierSearch"
            no-filter
          >
          <template v-slot:prepend-item>
            <VListItem
              v-if="filteredSupplierItems.length"
              @click="handleNewSupplierClick"
              class="new-supplier-btn"
              value=""
            >
              <VListItemContent>
                <VListItemTitle>+ {{$t("New Supplier")}}</VListItemTitle>
              </VListItemContent>
            </VListItem>
          </template>

          <template v-slot:no-data>
            <VListItem
              v-if="!filteredSupplierItems.length"
              @click="handleNewSupplierClick"
              class="new-supplier-btn"
              value=""
            >
              <VListItemContent>
                <VListItemTitle>+ {{$t("New Supplier")}}</VListItemTitle>
              </VListItemContent>
            </VListItem>
          </template>
        </AppAutocomplete>
        </VCol>

        <!-- 👉 Associated Contract -->
        <VCol cols="12" md="6">
          <AppAutocomplete
            :items="filteredContractItems"
            v-model="selectedContract"
            item-title="title" 
            item-value="id"
            clearable
            clear-icon="mdi-close"
            class="small"
            label="Associated Contract"
            @update:search="onContractSearch"
            no-filter
          />
        </VCol>

        <VCol cols="12" class="d-flex gap-4">
          <VBtn
            :disabled="true"
            color="secondary"
            variant="tonal"
          >
          <VIcon
            icon="tabler-arrow-left"
            start
            class="flip-in-rtl"
          />
            {{ $t('Previous') }}
          </VBtn>

          <VSpacer />
          <VBtn
            v-if="!addSavingsLoader"
            type="submit" 
          >
            {{ $t('Next') }}

            <VIcon
              icon="tabler-arrow-right"
              end
              class="flip-in-rtl"
            />
          </VBtn>
          <VBtn
            v-if="addSavingsLoader"
            variant="tonal"
            class="me-4"
          >
            <VProgressCircular
              :size="25"
              width="3"
              color="primary"
              indeterminate
            />
          </VBtn>

        </VCol>
      </VRow>
    </VForm>
  </VCardText>

<QuickCreateSupplier
  v-model:isDialogVisible="supplierCreateDialog"
  @response="refreshSupplier"
/>

<QuickCreateLocation
  v-model:isDialogVisible="locationCreateDialog"
  @response="refreshSupplier"
  :country="country"

/>

<QuickCreateDepartment
  v-model:isDrawerOpen="departmentCreateDialog"
  @response="refreshSupplier"
  :locationList="locationListData"

/>
</template>

<style lang="scss">

</style>
