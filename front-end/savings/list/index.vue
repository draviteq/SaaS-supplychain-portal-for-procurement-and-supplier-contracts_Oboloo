<script setup>
import Metrics from "@/components/Metrics.vue";
import { SavingsTableHeaders } from "@core/utils/tableHeaders";
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { paginationMeta } from "@/plugins/dashboards/utils";
import { useSavingsStore } from '@/router/stores/useSavingsStore'
import { useSnackBarStore } from "@core/utils/snackBar";
import { onMounted } from "vue";
import { formatDate } from "@/plugins/date/dateUtils";
import { avatarText, kFormatter } from "@core/utils/formatters";
import ApexChartLine from "@/views/charts/apex-chart/ApexChartLine.vue"
import ChartJsBarChart from "@/views/charts/chartjs/ChartJsBarChart.vue";
import { chartJsCustomColors } from "@core/utils/chartJsCustomColors";
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useUserListStore } from '@/router/stores/useUserListStore'
import useEmitter from "@/plugins/emitter/useEmitter"
import { useI18n } from "vue-i18n";
import { RelationshipType } from '@core/enums'
import { getFormattedCustomFieldValue } from "@/plugins/common";

const I18n = useI18n();
const emitter = useEmitter()
const userListStore = useUserListStore()
const savingsStore = useSavingsStore()
const snackBarStore = useSnackBarStore();
const searchQuery = ref('');
const tableLoader = ref(false);
const totalSavings = ref(0);
const savings = ref([]);
const exportSavingsRecords = ref([]);
const savingStartDate = ref(null);
const savingEndDate = ref(null);
const selectedStatus = ref(null);
const selectedLocation = ref([]);
const selectedDepartment = ref([]);
const selectedCategory = ref(null);
const selectedCurrency = ref(null);
const selectedCreatedBy = ref(null);

const selectedSavingType = ref(null);
const selectedSavingTypeGraph = ref([]);
const selectedSubCategory = ref(null);
const selectedArchiveStatus = ref([0]);
const archiveDialog = ref(false) // toggle archive dialog 
const archiveDialogTitle = ref("Archive Saving Record?") // toggle archive dialog Title
const archiveDialogSubTitle = ref("Are you sure you want to archive this saving record?") // toggle archive dialog SubTitle
const selectedSavingsRecord = ref();

/** filter items */
const categoryList = ref([]);
const currencyList = ref([]);
const savingTypeList = ref([]);
const createdBy = ref([]);
const subCategoryList = ref([]);
const populatedSubCategoryList = ref([]);
const populatedLocationList = ref([]);
const populatedDepartmentList = ref([]);
const populatedMetricDepartmentList = ref([]);
const populatedMetricLocationList = ref([]);
const locationList = ref([]);
const locationDeparmentList = ref([]);
const departmentList = ref([]);
const statusList = ref([]);
const archiveStatusList = ref([]);

const startDateGraph = ref(null)
const endDateGraph = ref(null)
const selectedCreatedByGraph = ref(null)
const selectedSavingProjectGraphFilter = ref()
const selectedSupplierGraphFilter = ref([]);

const selectedMetricLocation = ref([]);
const selectedMetricDepartment = ref([]);
const categoryListGraph = ref([]);
const subCategoryListGraph = ref([]);
const populatedMetricSubCategory = ref([]);
const populatedMetricCategory = ref([]);
const selectedMetricSubCategory = ref([]);
const selectedMetricCategory = ref([]);
const createdByList = ref([]);
const approvalStatusList = ref([]);
const selectedApprovalStatus = ref(null);
const savingProjectGraphFilterList = ref([])
const selectedSupplier = ref([]);

const savingNotifications = ref([]); // store contract recent notifications
const router = useRouter()
const loggedInUser = localStorage.getItem("loginUserId"); // log in user
const customFieldHeader = ref([]); // store custom field as header
const supplierList = ref([]);

// toggle the table columns
const selectedHeaders = (headers) => {
  savingsTableHeader.value = headers;

  const temp = savings.value;
  savings.value = [];
  savings.value = temp;
}

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
})

// initial function
onMounted(() => {
  fetchSavingFilters(); // fetch the filters options
  fetchSavingsExportData(); // fetch the saving export data
  fetchMetricsData(); // fetch the metrics data
  fetchProjectedVsRealisedByMonth(); // fetch the Projected Vs Realised Savings by Month  Graph data
  fetchProjectedVsRealisedByCategory(); // fetch the Projected Vs Realised Savings by Category  Graph data
  fetchProjectedVsRealisedByDepartment(); // fetch the Projected Vs Realised Savings by Department  Graph data
  fetchSavingDashboardNotification();

  emitter.on("refreshSavingsDashboard", () => {
    fetchMetricsData(); // fetch the metrics data
    fetchProjectedVsRealisedByMonth(); // fetch the Projected Vs Realised Savings by Month  Graph data
    fetchProjectedVsRealisedByCategory(); // fetch the Projected Vs Realised Savings by Category  Graph data
    fetchProjectedVsRealisedByDepartment(); // fetch the Projected Vs Realised Savings by Department  Graph data
  });

  window._hsq = window._hsq || [];
  window._hsq.push(["trackPageView"]); // track current page

});

watchDebounced(() => ({
  currentPage: options.value.page,
  itemsPerPage: options.value.itemsPerPage,
  searchQuery: searchQuery.value,
  startDate: savingStartDate.value,
  endDate: savingEndDate.value,
  status: selectedStatus.value,
  location: selectedLocation.value,
  department: selectedDepartment.value,
  category: selectedCategory.value,
  subCategory: selectedSubCategory.value,
  archive: selectedArchiveStatus.value,
  currency: selectedCurrency.value,
  createdBy: selectedCreatedBy.value,
  saving_type: selectedSavingType.value,
  approval_status: selectedApprovalStatus.value,
  supplier_ids: selectedSupplier.value,
}),
  (v) => {
    // fetchSavingFilters();
    fetchSavingRecords(); // fetch saving records
  },
  { debounce: 800 },
)


// for the contact dashboard graphs
watchDebounced(() => ({
  location : selectedMetricLocation.value,
  department : selectedMetricDepartment.value,
  category : selectedMetricCategory.value,
  sub_category : selectedMetricSubCategory.value,
  created_by : selectedCreatedByGraph.value,
  start_date : startDateGraph.value,
  end_date : endDateGraph.value,
  saving_type: selectedSavingTypeGraph.value,
  saving_project_id: selectedSavingProjectGraphFilter.value,
  supplier_ids: selectedSupplierGraphFilter.value,
}),
  (v) => {
    fetchMetricsData(); // fetch the metrics data
    fetchProjectedVsRealisedByMonth(); // fetch the Projected Vs Realised Savings by Month  Graph data
    fetchProjectedVsRealisedByCategory(); // fetch the Projected Vs Realised Savings by Category  Graph data
    fetchProjectedVsRealisedByDepartment(); // fetch the Projected Vs Realised Savings by Department  Graph data
  },
  { debounce: 800 },
)

// fetch saving records
const fetchSavingRecords = () => {
  tableLoader.value = true;
  savingsStore.fetchSavingRecords({
    search: searchQuery.value, 
    options: options.value,
    startDate: savingStartDate.value,
    endDate: savingEndDate.value,
    status: selectedStatus.value,
    location: selectedLocation.value,
    department: selectedDepartment.value,
    category: selectedCategory.value,
    subCategory: selectedSubCategory.value,
    archive: selectedArchiveStatus.value,
    currency: selectedCurrency.value,
    createdBy: selectedCreatedBy.value,
    saving_type: selectedSavingType.value,
    approval_status: selectedApprovalStatus.value,
    supplier_ids: selectedSupplier.value,
  }).then((response) => {
    const { savingsData, page, total } = response.data; 
    savings.value = savingsData; 
    totalSavings.value = total;
    options.value.page = +page;

  }).catch((error) => {
    const { message, errors: errorData} = error.response.data
    snackBarStore.error = errorData;
    snackBarStore.message = message;
    snackBarStore.type = 'error';

    snackBarStore.snackbar = true;
  }).finally(() => {
    tableLoader.value = false;
  });
}

const isCustomFieldLoaded = ref(true);

// fetch saving filters
const fetchSavingFilters = () => {

  isCustomFieldLoaded.value = false;

  savingsStore.fetchSavingFilters().then((response) => {
    const { categories, SubCategory, departments, createdBy, locations, departmentsLocations, status, archiveStatus, currency, savingType, approvalStatus, savingFilterList, custom_fields, suppliers } = response.data;

    categoryList.value = categories;
    createdByList.value = createdBy;
    subCategoryList.value = SubCategory;
    locationList.value = locations;
    locationDeparmentList.value = departmentsLocations;
    departmentList.value = departments;
    statusList.value = status;
    archiveStatusList.value = archiveStatus;
    currencyList.value = currency;
    savingTypeList.value = savingType;
    populatedLocationList.value = locationList.value;
    populatedDepartmentList.value = departmentList.value;
    populatedMetricDepartmentList.value = departmentList.value;
    populatedMetricLocationList.value = locationList.value;
    subCategoryListGraph.value =  subCategoryList.value;
    categoryListGraph.value = categoryList.value;
    // populatedMetricSubCategory.value = subCategoryList.value;
    populatedMetricCategory.value = categoryList.value;
    approvalStatusList.value = approvalStatus;

    savingProjectGraphFilterList.value = savingFilterList || [];
    customFieldHeader.value = custom_fields || [];
    supplierList.value = suppliers || [];

  }).catch((error) => {
    const { message, errors: errorData} = error.response.data
    snackBarStore.error = errorData;
    snackBarStore.message = message;
    snackBarStore.type = 'error';

    snackBarStore.snackbar = true;
  })
  .finally(() => {
    isCustomFieldLoaded.value = true;
  });
}

const savingsTableModifiedHeaders = computed(() => {
  const tempHeader = [...SavingsTableHeaders];
  
  customFieldHeader.value.forEach(item => {
    tempHeader.push({
      title: item.field_name,
      key: customHeaderKey(item),
      sortable: false,
      visible: false,
      fixed: false
    });
  })

  // Filter out the actions object
  const filteredArr = tempHeader.filter(item => item.key != "actions");

  // Find the actions object
  const actionsObj = tempHeader.find(item => item.key == "actions");

  // Append the actions object at the end if it exists
  if (actionsObj) {
    filteredArr.push(actionsObj);
  }

  return filteredArr;
});

const savingsTableHeader = ref(structuredClone(toRaw(savingsTableModifiedHeaders.value.filter(item => item.visible))))

const SavingsTableHeaderTrans = computed(() => {
  const tempHeader = [];

  savingsTableHeader.value.forEach(item => {
    tempHeader.push({
      title: I18n.t(item.title),
      key: item.key,
      sortable: item.sortable,
      visible: item.visible,
      fixed: item.fixed
    });
  })

  return tempHeader;
})

const customHeaderKey = (header) => {
  return header?.field_name?.split(' ')?.join('_')+'_'+header?.id;
}

const customHeaderName = (header) => {
  return header?.field_name?.split(' ')?.join('_');
}

const totalProjectSavingMetric = ref(0) // End Date Over 3 Months
const totalProjectSavingPercMetric = ref(0) // End Date Within 3 Months
const totalRealisedSavingMetric = ref(0) // End Date Within 30 Days
const totalRealisedSavingPercMetric = ref(0) // Passed Contract End Date

const currencySymbol = ref('');

const isSavingProjectVsRealisedByMonthLoaded = ref(false);
const savingProjectVsRealisedByMonth = ref({
  labels: [],
  xaxisTitle: '',
  yaxisTitle: '',
  prefix: '',
  suffix: '',
  datasets: [],
  backgroundColor: [chartJsCustomColors.warning, chartJsCustomColors.success]
});

const fetchProjectedVsRealisedByMonth = () => {
  isSavingProjectVsRealisedByMonthLoaded.value = false;

  savingsStore.fetchProjectedVsRealisedByMonth({
    location : selectedMetricLocation.value,
    department : selectedMetricDepartment.value,
    category : selectedMetricCategory.value,
    sub_category : selectedMetricSubCategory.value,
    created_by : selectedCreatedByGraph.value,
    start_date : startDateGraph.value,
    end_date : endDateGraph.value,
    saving_type: selectedSavingTypeGraph.value,
    saving_project_id: selectedSavingProjectGraphFilter.value,
    supplier_ids: selectedSupplierGraphFilter.value,
  })
  .then(response => {

    const { savingsData, current_symbol } = response.data;

    const labels = savingsData.map(item => item.month_and_year);

    const projectdSavings = {
      name: "Projected Savings",
      data: new Array(labels.length).fill(0),
    }

    const realisedSavings = {
      name: "Realised Savings",
      data: new Array(labels.length).fill(0),
    }

    for(const item of savingsData)
    {
      projectdSavings['data'][labels.indexOf(item.month_and_year)] = item.total_project_saving.toFixed(2);
      realisedSavings['data'][labels.indexOf(item.month_and_year)] = item.total_realised_saving.toFixed(2);
    }

    savingProjectVsRealisedByMonth.value['labels'] = labels;
    savingProjectVsRealisedByMonth.value['datasets'] = [projectdSavings, realisedSavings];
    savingProjectVsRealisedByMonth.value['prefix'] = current_symbol;

  })
  .catch(error => {
    console.error(error)
  })
  .finally(() => {
    isSavingProjectVsRealisedByMonthLoaded.value = true;
  })
}

const isSavingsByCategoryLoaded = ref(false);

const savingsByCategory = ref({
  labels: [],
  datasets: [],
  backgroundColor: [chartJsCustomColors.warning, chartJsCustomColors.success],
  prefix: '',
});

const fetchProjectedVsRealisedByCategory = () => {
  isSavingsByCategoryLoaded.value = false;

  savingsStore.fetchProjectedVsRealisedByCategory({
    location : selectedMetricLocation.value,
    department : selectedMetricDepartment.value,
    category : selectedMetricCategory.value,
    sub_category : selectedMetricSubCategory.value,
    created_by : selectedCreatedByGraph.value,
    start_date : startDateGraph.value,
    end_date : endDateGraph.value,
    saving_type: selectedSavingTypeGraph.value,
    saving_project_id: selectedSavingProjectGraphFilter.value,
    supplier_ids: selectedSupplierGraphFilter.value,
  })
  .then(response => {

    const { savingsData, current_symbol } = response.data;

    const labels = savingsData.map(item => item.value);

    const projectdSavings = {
      maxBarThickness: 25,
      label: "Projected Savings",
      backgroundColor: chartJsCustomColors.warning,
      borderColor: "transparent",
      data: new Array(labels.length).fill(0),
    }

    const realisedSavings = {
      maxBarThickness: 25,
      label: "Realised Savings",
      backgroundColor: chartJsCustomColors.success,
      borderColor: "transparent",
      data: new Array(labels.length).fill(0),
    }

    for(const item of savingsData)
    {
      projectdSavings['data'][labels.indexOf(item.value)] = item.total_project_saving.toFixed(2);
      realisedSavings['data'][labels.indexOf(item.value)] = item.total_realised_saving.toFixed(2);
    }

    savingsByCategory.value['labels'] = labels;
    savingsByCategory.value['datasets'] = [projectdSavings, realisedSavings];
    savingsByCategory.value['prefix'] = current_symbol || '';

  })
  .catch(error => {
    console.error(error)
  })
  .finally(() => {
    isSavingsByCategoryLoaded.value = true;
  })
}


const isSavingsByDepartmentLoaded = ref(false);

const savingsByDepartment = ref({
  labels: [],
  datasets: [],
  backgroundColor: [chartJsCustomColors.warning, chartJsCustomColors.success]
});

const fetchProjectedVsRealisedByDepartment = () => {
  isSavingsByDepartmentLoaded.value = false;

  savingsStore.fetchProjectedVsRealisedByDepartment({
    location : selectedMetricLocation.value,
    department : selectedMetricDepartment.value,
    category : selectedMetricCategory.value,
    sub_category : selectedMetricSubCategory.value,
    created_by : selectedCreatedByGraph.value,
    start_date : startDateGraph.value,
    end_date : endDateGraph.value,
    saving_type: selectedSavingTypeGraph.value,
    saving_project_id: selectedSavingProjectGraphFilter.value,
    supplier_ids: selectedSupplierGraphFilter.value,
  })
  .then(response => {

    const { savingsData, current_symbol } = response.data;

    const labels = Object.values(savingsData).map(item => item.department_name);

    const projectdSavings = {
      maxBarThickness: 25,
      label: "Projected Savings",
      backgroundColor: chartJsCustomColors.warning,
      borderColor: "transparent",
      data: new Array(labels.length).fill(0),
    }

    const realisedSavings = {
      maxBarThickness: 25,
      label: "Realised Savings",
      backgroundColor: chartJsCustomColors.success,
      borderColor: "transparent",
      data: new Array(labels.length).fill(0),
    }

    for(const item of Object.values(savingsData))
    {
      projectdSavings['data'][labels.indexOf(item.department_name)] = item.total_project_saving_sum.toFixed(2);
      realisedSavings['data'][labels.indexOf(item.department_name)] = item.total_realised_saving_sum.toFixed(2);
    }

    // Combine labels and both datasets into an array of objects
    // let combined = labels.map((label, index) => {
    //     return {
    //         label: label,
    //         projected: projectdSavings['data'][index],
    //         realised: realisedSavings['data'][index]
    //     };
    // });

    // // Sort the combined array by the 'projected' field (or 'realised', depending on your needs)
    // combined.sort((a, b) => a.projected - b.projected);

    // console.log("combined", combined);

    // return;

    savingsByDepartment.value['labels'] = labels;
    savingsByDepartment.value['datasets'] = [projectdSavings, realisedSavings];
    savingsByDepartment.value['prefix'] = current_symbol || '';

    savingsByDepartment.value = sortChartData(savingsByDepartment.value); // sort the chart data by project savings in descending order

  })
  .catch(error => {
    console.error(error)
  })
  .finally(() => {
    isSavingsByDepartmentLoaded.value = true;
  })
}

// sort the chart data using first data of datasets 
const sortChartData = (chartData) => {
  // Combine labels and both datasets into an array of objects
  let combined = chartData.labels.map((label, index) => {
      return {
          label: label,
          projected: parseFloat(chartData.datasets[0].data[index]),
          realised: parseFloat(chartData.datasets[1].data[index])
      };
  });

  // Sort the combined array by the 'projected' field
  combined.sort((a, b) => b.projected - a.projected);

  // Separate the sorted array back into labels and both datasets
  chartData.labels = combined.map(item => item.label);
  chartData.datasets[0].data = combined.map(item => item.projected.toFixed(2));  // Convert back to string with two decimals
  chartData.datasets[1].data = combined.map(item => item.realised.toFixed(2));

  return chartData;
}

const fetchMetricsData = () => {
  savingsStore.fetchSavingsMetricData({
    location : selectedMetricLocation.value,
    department : selectedMetricDepartment.value,
    category : selectedMetricCategory.value,
    sub_category : selectedMetricSubCategory.value,
    created_by : selectedCreatedByGraph.value,
    start_date : startDateGraph.value,
    end_date : endDateGraph.value,
    saving_type: selectedSavingTypeGraph.value,
    saving_project_id: selectedSavingProjectGraphFilter.value,
    supplier_ids: selectedSupplierGraphFilter.value,
  })
  .then(response => {

    const { total_project_saving, total_project_saving_perc, total_realised_saving, total_realised_saving_perc, currency_symbol } = response.data.matricData;

    totalProjectSavingMetric.value = total_project_saving || 0;
    totalProjectSavingPercMetric.value = total_project_saving_perc || 0;
    totalRealisedSavingMetric.value = total_realised_saving || 0;
    totalRealisedSavingPercMetric.value = total_realised_saving_perc || 0;
    currencySymbol.value = currency_symbol;
  })
  .catch(error => {
    console.error(error)
  })
  .finally(() => {
    // loadChartdata.value = true;
  })
}

const metricsData = [
  {
    // icon: 'tabler-user',
    title: 'Total Realised Savings',
    stats: totalRealisedSavingMetric,
    prefix: currencySymbol,
    backgroundColor: '#48d6a8',
  },
  {
    // icon: 'tabler-user',
    title: 'Realised Savings %',
    percentage: totalRealisedSavingPercMetric,
    suffix: ref('%'),
    backgroundColor: '#48d6a8',
  },
  {
    // icon: 'tabler-user-check',
    title: 'Remaining Projected Savings',
    stats: totalProjectSavingMetric,
    prefix: currencySymbol,
    backgroundColor: '#efa65f',
  },
  {
    // icon: 'tabler-user-exclamation',
    title: 'Remaining Projected Savings %',
    percentage: totalProjectSavingPercMetric,
    suffix: ref('%'),
    backgroundColor: '#efa65f',
  },
]


const clearFilters = () => {

  savingStartDate.value = null;
  savingEndDate.value = null;
  selectedStatus.value = [];
  selectedLocation.value = [];
  selectedDepartment.value = [];
  selectedCategory.value = [];
  // selectedSubCategory.value = null;
  selectedArchiveStatus.value = [0];
  selectedCurrency.value =  [];
  selectedCreatedBy.value =  [];
  selectedApprovalStatus.value =  [];

  selectedSavingType.value =  [];
  selectedSubCategory.value = [];
  populatedSubCategoryList.value = selectedSubCategory.value;
  populatedDepartmentList.value = departmentList.value;
  populatedLocationList.value = locationList.value; 
  selectedSupplier.value = [];
}

// populated sub category for selected category
const populateSubCategory = (val) => {
  if(val && val.length > 0) {
    populatedSubCategoryList.value = subCategoryList.value.filter(item => val.includes(item.code));
  }else{
    populatedSubCategoryList.value = [];
  }
  selectedSubCategory.value = [];
}

// populated department for selected location
const populateDepartment = (location) => {
  if(location && location.length > 0){
    const departments = locationDeparmentList.value.filter(item => location.includes(item.location_id)).map(item => item.department_id);
    const updatedList = departmentList.value.filter(item => (departments.includes(item.id) || selectedDepartment.value.includes(item.id) ));

    populatedDepartmentList.value = updatedList;
  }else{
    populatedDepartmentList.value = departmentList.value;
  }
}

// populated location for selected department
const populateLocation = (department) => {
  if(department && department.length > 0){
    const locations = locationDeparmentList.value.filter(item => department.includes(item.department_id)).map(item => item.location_id);
    const updatedList = locationList.value.filter(item => (locations.includes(item.id) || selectedLocation.value.includes(item.id)));

    populatedLocationList.value = updatedList;
  }else{
    populatedLocationList.value = locationList.value;
  }
}

const evaluateStatus = (saving) => {

  const status = saving.is_draft == 1 ? 4 : saving.status;

  if(status == 0)
  {
    return {
      color: 'error',
      title: 'Not Started',
    }
  }
  else if(status == 1) {
    return {
      color: 'warning',
      title: 'In Progress',
    }
  }
  else if(status == 2) {
    return {
      color: 'success',
      title: 'Completed',
    }
  }
  else if(status == 3){
    return {
      color: 'primary',
      title: 'Cancelled',
    }
  }
  else{
    return {
      color: 'secondary',
      title: 'Draft',
    }
  }
}

const showArchiveDialog = (saving) => {
  selectedSavingsRecord.value = saving;

  if(selectedSavingsRecord.value.archive == 0){
      archiveDialogTitle.value = "Archive Saving Record?";
      archiveDialogSubTitle.value = "Are you sure you want to archive this saving record?";
  }
  else{
    archiveDialogTitle.value = "Unarchive Saving Record?";
    archiveDialogSubTitle.value = "Are you sure you want to unarchive this saving record?";
  }

  archiveDialog.value = true;
}

const toggleArchiveSavings = () => {

  
  const postData = {
    id: selectedSavingsRecord.value.id,
    archive_status: selectedSavingsRecord.value.archive == 0 ? 1 : 0,
  };

  savingsStore.changeStatus(postData)
  .then((response) => {
    snackBarStore.message = response.data.message;
    snackBarStore.type = 'success';

  })
  .catch((error) => {
    const { message, errors: errorData} = error.response.data
    snackBarStore.error = errorData;
    snackBarStore.message = message;
    snackBarStore.type = 'error';
  })
  .finally(() => {
    snackBarStore.snackbar = true;

    fetchSavingRecords(); // fetch saving records

    // refresh the visual data 
    fetchMetricsData(); // fetch the metrics data
    fetchProjectedVsRealisedByMonth(); // fetch the Projected Vs Realised Savings by Month  Graph data
    fetchProjectedVsRealisedByCategory(); // fetch the Projected Vs Realised Savings by Category  Graph data
    fetchProjectedVsRealisedByDepartment(); // fetch the Projected Vs Realised Savings by Department  Graph data

  });
}

// fetch saving export data
const fetchSavingsExportData = () => {
  savingsStore.fetchSavingsExportRecords()
  .then((response) => {
    const { savingsData } = response.data;
    
    for(const savings of savingsData) {
      let childNum = 1; 
      const temp = {};
      temp['number'] = `=\"${savings.id+''}\"`;
      temp['saving_title'] = `\"${savings.name || '--'}\"`;
      temp['description_/_notes'] = `\"${savings.description || '--'}\"`;
      temp['savings_type'] = `\"${savings?.saving_types?.name || '--'}\"`;
      temp['status'] = `\"${evaluateStatus(savings).title || '--'}\"`;
      temp['record_type'] = `\"Saving Activity\"`;
      temp['currency'] = `\"${savings?.currency?.currency || '--'}\"`;
      temp['exchange_rate_against_base_currency'] = `\"${'--'}\"`;
      temp['category'] = `\"${savings?.category?.value || '--'}\"`;
      temp['sub_category'] = `\"${savings?.subcategory?.value || '--'}\"`;
      temp['location'] = `\"${[...new Set(savings.get_contract_location_department.map(item=> item.get_dpartment_location?.locations?.location_name))].join(', ') || '--'}\"`;
      temp['department'] = `\"${[...new Set(savings.get_contract_location_department.map(item=> item.get_dpartment_location?.departments?.department_name))].join(', ') || '--'}\"`;
      temp['supplier_name'] = `\"${savings?.supplier?.name || '--'}\"`;
      temp['associated_contract'] = `\"${savings?.contract?.title || '--' || '--'}\"`;
      temp['saving_parent_/_child_relationship'] = `\"${getRelationType(savings?.parent_child?.relation_type)}\"`;
      temp['parent_/_child_of_saving'] = `\"${savings?.parent_child?.saving_record?.name || '--'}\"`;
      temp['total_baseline_spend'] = `\"${savings?.total_base_line_spend?.toFixed(2) || '--' || '--'}\"`; 
      temp['milestone_baseline_spend'] = `\"${'--'}\"`;
      temp['total_projected_cost_reduction'] = `\"${savings.project_cost_reduction?.toFixed(2)|| '--'}\"`;
      temp['milestone_projected_cost_reduction'] = `\"${'--'}\"`;
      temp['total_projected_cost_avoidance'] = `\"${savings.project_cost_avoidance?.toFixed(2) || '--'}\"`;
      temp['milestone_projected_cost_avoidance'] = `\"${'--'}\"`;
      temp['total_projected_savings'] = `\"${savings?.total_project_saving?.toFixed(2) || '--'}\"`;
      temp['milestone_projected_saving'] = `\"${'--'}\"`;
      temp['total_projected_savings_(%)'] = `\"${savings?.total_project_saving_perc?.toFixed(2) || '--'}\"`;
      temp['milestone_projected_saving_(%)'] = `\"${'--'}\"`;
      temp['total_realised_cost_reduction'] = `\"${savings.realised_cost_reduction?.toFixed(2) || '--'}\"`;
      temp['milestone_realised_cost_reduction'] = `\"${'--'}\"`;
      temp['total_realised_cost_avoidance'] = `\"${savings.realised_cost_avoidance?.toFixed(2) || '--'}\"`;
      temp['milestone_realised_cost_avoidance'] = `\"${'--'}\"`;
      temp['total_realised_savings'] = `\"${savings?.total_realised_saving?.toFixed(2) || '--'}\"`;
      temp['milestone_realised_saving'] = `\"${'--'}\"`;
      temp['total_realised_savings_(%)'] = `\"${savings?.total_realised_saving_perc?.toFixed(2) || '--'}\"`;
      temp['milestone_realised_saving_(%)'] = `\"${'--'}\"`;
      temp['total_baseline_spend_against_realised_savings'] = `\"${savings?.total_realised_saving_base_line?.toFixed(2) || '--'}\"`;
      temp['due_date'] = `\"${formatDate(savings?.end_date) || '--'}\"`;
      temp['created_on'] = `\"${formatDate(savings?.created_at) || '--'}\"`;
      temp['created_by'] = `\"${savings?.user?.name || '--'}\"`;

      savings?.custom_fields?.forEach(el => {
        temp[customHeaderName(el)] = el.formatted_value || '--';
      });

      savings?.milestones?.forEach(milestone => {
          milestone?.custom_fields?.forEach(el => {
              temp[customHeaderName(el)] = '--';
          });
      });

      exportSavingsRecords.value.push(temp);

      for(const milestone of savings.milestones){
        const childTemp = {};
        childTemp['number'] = `=\"${(savings.id + '.' + childNum).toString()}\"`;
        childTemp['saving_title'] = `\"${milestone?.name || '--'}\"`;
        childTemp['description_/_notes'] = `\"${' '}\"`;
        childTemp['savings_type'] = `\"${' '}\"`;
        childTemp['status'] = `\"${milestone.status == 1 ? 'In Progress' : milestone.status == 2 ? 'Completed' : 'Cancelled' || '--'}\"`;
        childTemp['record_type'] = `\"Milestone\"`;
        childTemp['currency'] = `\"${savings?.currency?.currency || '--'}\"`;
        childTemp['exchange_rate_against_base_currency'] = `\"${milestone?.currency_rate?.toFixed(2) || '--'}\"`;
        childTemp['category'] = `\"${savings?.category?.value || '--'}\"`;
        childTemp['sub_category'] = `\"${savings?.subcategory?.value || '--'}\"`;
        childTemp['location'] = `\"${[...new Set(savings.get_contract_location_department.map(item=> item.get_dpartment_location?.locations?.location_name))].join(', ') || '--'}\"`;
        childTemp['department'] = `\"${[...new Set(savings.get_contract_location_department.map(item=> item.get_dpartment_location?.departments?.department_name))].join(', ') || '--'}\"`;
        childTemp['supplier_name'] = `\"${'--'}\"`;
        childTemp['associated_contract'] = `\"${'--'}\"`;
        childTemp['saving_parent_/_child_relationship'] = `\"${'--'}\"`;
        childTemp['parent_/_child_of_saving'] = `\"${'--'}\"`;
        childTemp['total_baseline_spend'] = `\"${'--'}\"`;
        childTemp['milestone_baseline_spend'] = `\"${milestone?.base_line_spend?.toFixed(2) || '--'}\"`;
        childTemp['total_projected_cost_reduction'] = `\"${'--'}\"`;
        childTemp['milestone_projected_cost_reduction'] = `\"${milestone.project_cost_reduction?.toFixed(2) || '--'}\"`;
        childTemp['total_projected_cost_avoidance'] = `\"${'--'}\"`;
        childTemp['milestone_projected_cost_avoidance'] = `\"${milestone.project_cost_avoidance?.toFixed(2) || '--'}\"`;
        childTemp['total_projected_savings'] = `\"${'--'}\"`;
        childTemp['milestone_projected_saving'] = `\"${milestone?.total_project_saving?.toFixed(2) || '--'}\"`;
        childTemp['total_projected_savings_(%)'] = `\"${'--'}\"`;
        childTemp['milestone_projected_saving_(%)'] = `\"${milestone?.total_project_saving_perc?.toFixed(2) || '--'}\"`;
        childTemp['total_realised_cost_reduction'] = `\"${'--'}\"`;
        childTemp['milestone_realised_cost_reduction'] = `\"${milestone?.realised_cost_reduction?.toFixed(2) || '--'}\"`;
        childTemp['total_realised_cost_avoidance'] = `\"${'--'}\"`;
        childTemp['milestone_realised_cost_avoidance'] = `\"${milestone?.realised_cost_avoidance?.toFixed(2) || '--'}\"`;
        childTemp['total_realised_savings'] = `\"${'--'}\"`;
        childTemp['milestone_realised_saving'] = `\"${milestone?.total_realised_saving?.toFixed(2) || '--'}\"`;
        childTemp['total_realised_savings_(%)'] = `\"${'--'}\"`;
        childTemp['milestone_realised_saving_(%)'] = `\"${milestone?.total_realised_saving_perc?.toFixed(2) || '--'}\"`;
        childTemp['total_baseline_spend_against_realised_savings'] = `\"${'--'}\"`;
        childTemp['due_date'] = `\"${formatDate(milestone?.end_date) || '--'}\"`;
        childTemp['created_on'] = `\"${formatDate(milestone?.created_at) || '--'}\"`;
        childTemp['created_by'] = `\"${savings?.user?.name || '--'}\"`;

        savings?.custom_fields?.forEach(el => {
          childTemp[customHeaderName(el)] = '--';
        });

        milestone?.custom_fields?.forEach(el => {
          childTemp[customHeaderName(el)] = el.formatted_value || '--';
        });

        childNum++;
        exportSavingsRecords.value.push(childTemp);

      }

    }

  }).catch((error) => {
      console.log("error", error);
      
  });
}


// export savings records  
const exportSavings = () => {
  const data = arrayToCsv(exportSavingsRecords.value);
  downloadBlob(data, 'savings.csv', 'text/csv;charset=utf-8;')
}

const arrayToCsv = (data) => { // convert array to csv format 
  const array = [Object.keys(data[0]).map(h => h.toString().split('_').join(' ').toUpperCase())].concat(data)

  return array.map(it => {
      return Object.values(it).toString() // for tab saparated delimeter add (.join('\t')) after values(it)
  }).join('\n');
}

const downloadBlob = (content, filename, contentType) => { // convert content and download
  // Create a blob
  var blob = new Blob([content], { type: contentType });
  var url = URL.createObjectURL(blob);

  // Create a link to download it
  var pom = document.createElement('a');
  pom.href = url;
  pom.setAttribute('download', filename);
  pom.click();
}

// add class to the archive records
const rowHighlightClass = (saving)=>{
  if(selectedArchiveStatus.value.length == 1 && saving && saving.archive == 1 ){
    return { 'highlight-row': true };
  }
}

// clear filter of contract dashboard graphs
const clearFilterGraph = () => {
  selectedMetricLocation.value = null;
  selectedMetricCategory.value = null;
  selectedMetricSubCategory.value = null;
  selectedMetricDepartment.value = null;
  selectedCreatedByGraph.value = null;
  selectedSavingProjectGraphFilter.value = null;
  startDateGraph.value = null;
  endDateGraph.value = null;

  selectedSavingTypeGraph.value = null;

  selectedMetricSubCategory.value = [];
  populatedMetricLocationList.value = locationList.value;
  populatedMetricDepartmentList.value = departmentList.value;
  populatedMetricSubCategory.value = selectedMetricSubCategory.value;
  selectedSupplierGraphFilter.value = [];
}


const populateMetricDepartment = (location) => {

  if(location && location.length > 0) {

    const departments = locationDeparmentList.value.filter(item => location.includes(item.location_id)).map(item => item.department_id);
    const updatedList = departmentList.value.filter(item => (departments.includes(item.id)));
    populatedMetricDepartmentList.value = updatedList;
  }else{
    populatedMetricDepartmentList.value = departmentList.value;
  }
}

const populateMetricLocation = (department) => {
  if(department && department.length > 0) {
    const locations = locationDeparmentList.value.filter(item => department.includes(item.department_id)).map(item => item.location_id);
    const updatedList = locationList.value.filter(item => (locations?.includes(item?.id) || selectedMetricLocation.value?.includes(item?.id)));
    populatedMetricLocationList.value = updatedList;
  }else{
    populatedMetricLocationList.value = locationList.value;
  }
}

const populateMetricSubCategory = (categories) => {

  if(categories && categories.length > 0) {
    populatedMetricSubCategory.value = subCategoryListGraph.value.filter(item => categories.includes(item.code));
  }else{
    populatedMetricSubCategory.value = [];
  }
    selectedMetricSubCategory.value = [];
  
}

const fetchSavingDashboardNotification = () => {
  savingsStore
    .fetchSavingDashboardNotification()
    .then((response) => {
      const { notifications } = response.data;
      savingNotifications.value = notifications;
    })
    .catch((error) => {
      console.error(error);
    })
    .finally(() => {
    });
};

  const viewAllNotification = ()=>{
    router.replace({
      name: 'user-profile-tab',
      params: {
        tab: 'notification',
      },
    });
  }

const savingCurrencyFormatter = (val) => {
  return val ? val.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }): 0;
}

const getRelationType = (type) => {
  if(type == RelationshipType.CHILD)
    return 'Child';
  if(type == RelationshipType.PARENT)
    return 'Parent';

  return '--';
};

</script>

<template>
  <section>
    <VRow>
      <!-- metric data -->
      <Metrics :metrics-data="metricsData" />
      <VCol cols="12">
        <VCard>
          <VCardText>
            <VRow>
              <VCol cols="12">
                <VRow>
                  <VCol cols="12" md="2" sm="4">
                    <AppDateTimePicker  
                      v-model="startDateGraph"
                      :label="$t('Savings Start Date')"
                      class="app-date-time-picker"
                      clearable
                      clear-icon="mdi-close"
                    />
                  </VCol>
                  <VCol cols="12" md="2" sm="4">
                    <AppDateTimePicker  
                      v-model="endDateGraph"
                      :label="$t('Savings End Date')"
                      class="app-date-time-picker"
                      clearable
                      clear-icon="mdi-close"
                    />
                  </VCol>
                 
                  <VCol cols="12" md="2" sm="6">
                    <AppAutocomplete
                      v-model="selectedMetricCategory"
                      :items="categoryList"
                      item-value="id"
                      item-title="value"
                      :label="$t('Categories')"
                      clearable
                      chips
                      closable-chips
                      multiple
                      clear-icon="mdi-close"
                      class="small"
                      @update:model-value="populateMetricSubCategory"
                    />
                  </VCol>

                  <VCol cols="12" md="2" sm="6">
                    <AppAutocomplete
                      v-model="selectedMetricSubCategory"
                      :items="populatedMetricSubCategory"
                      item-value="id"
                      :label="$t('Sub-Categories')"
                      item-title="value"
                      clearable
                      chips
                      closable-chips
                      multiple
                      clear-icon="mdi-close"
                      class="small"
                    />
                  </VCol>

                  <VCol cols="12" md="2" sm="6">
                    <AppAutocomplete
                      v-model="selectedMetricLocation"
                      :items="populatedMetricLocationList"
                      item-value="id"
                      item-title="location_name"
                      :label="$t('Locations')"
                      clearable
                      clear-icon="mdi-close"
                      class="small"
                      @update:model-value="populateMetricDepartment"
                      closable-chips
                      chips
                      multiple
                    />
                  </VCol>
                  <VCol cols="12" md="2" sm="6">
                    <AppAutocomplete
                      v-model="selectedMetricDepartment"
                      :items="populatedMetricDepartmentList"
                      item-value="id"
                      item-title="department_name"
                      :label="$t('Departments')"
                      clearable
                      clear-icon="mdi-close"
                      class="small"
                      @update:model-value="populateMetricLocation"
                      closable-chips
                      chips
                      multiple
                    />
                  </VCol>
                  
                </VRow>
              </VCol>
              <VCol cols="12">
                <VRow>
                  <VCol cols="12" md="2" sm="4">
                    <AppAutocomplete
                      v-model="selectedSavingTypeGraph"
                      :items="savingTypeList"
                      item-value="id"
                      item-title="name"
                      :label="$t('Saving Type')"
                      clearable
                      clear-icon="mdi-close"
                      class="small"
                      closable-chips
                      chips
                      multiple
                    />
                  </VCol>

                  <VCol cols="12" md="2" sm="4">
                    <AppAutocomplete
                      v-model="selectedCreatedByGraph"
                      :items="createdByList"
                      item-value="id"
                      item-title="name"
                      clearable
                      chips
                      closable-chips
                      multiple
                      clear-icon="mdi-close"
                      class="small"
                      label="Created By"
                    />
                  </VCol> 

                  <VCol cols="12" md="2" sm="4">
                    <AppAutocomplete
                      v-model="selectedSavingProjectGraphFilter"
                      :items="savingProjectGraphFilterList"
                      item-value="id"
                      item-title="id_name"
                      clearable
                      clear-icon="mdi-close"
                      class="small"
                      label="Savings Project"
                    />
                  </VCol> 

                  <VCol cols="12" md="2" sm="4">
                    <AppAutocomplete
                      v-model="selectedSupplierGraphFilter"
                      :items="supplierList"
                      item-value="id"
                      item-title="name"
                      clearable
                      chips
                      closable-chips
                      multiple
                      clear-icon="mdi-close"
                      class="small"
                      label="Supplier"
                    />
                  </VCol> 

                  <VCol cols="12" md="4" sm="12" class="d-flex flex-wrap gap-3">
                    <VSpacer />
                    <VBtn @click="clearFilterGraph" variant="tonal" color="error">
                      {{ $t('Clear Filter') }}
                    </VBtn>
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- 👉 Projected Vs Realised Savings by Month Chart -->
      <VCol cols="12">
        <VCard :title="$t('Projected Vs Realised Savings by Month')">
          <VCardText>
            <ApexChartLine v-if="isSavingProjectVsRealisedByMonthLoaded" :data="savingProjectVsRealisedByMonth" :height="400" />
          </VCardText>
        </VCard>
      </VCol> 
      
      <VCol cols="12">
        <VRow>
          <!-- 👉 Recent Contract Notifications Chart -->
          <VCol cols="12" md="6">
            <VCard :title="$t('Recent Saving Notifications')" height="100%">

              <template #append>
                <div class="me-n2">
                  <VBtn 
                    color="success" 
                    variant="tonal" 
                    text 
                    @click="viewAllNotification"
                  >
                    {{ $t('All Notifications') }}
                  </VBtn>
                </div>
              </template>

              <VCardText>
                <RecentDashboardNotifications :notifications="savingNotifications"/>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="6">
            <VRow>
              <!-- 👉 Projected Vs Realised Savings by Category -->
              <VCol cols="12">
                <VCard :title="$t('Projected Vs Realised Savings by Category')">
                  <VCardText>
                    <ChartJsBarChart v-if="isSavingsByCategoryLoaded" :data="savingsByCategory" :height="325" />
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
            <VRow>
              <!-- 👉 Projected Vs Realised Savings By Department -->
              <VCol cols="12">
                <VCard :title="$t('Projected Vs Realised Savings By Department')">
                  <VCardText>
                    <ChartJsBarChart v-if="isSavingsByDepartmentLoaded" :data="savingsByDepartment" :height="325" />
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VCol>
        </VRow>
      </VCol> 

      <VCol cols="12">
        <VCard :title="$t('Savings')">
          <!-- 👉 Filters --> 
        
                <!-- Line 1 -->
                <VCardText class="d-flex flex-wrap gap-5">
                    <VRow>
                        <VCol cols="12">
                            <VRow>
                                <VCol cols="12" md="2" sm="4">
                                  <AppDateTimePicker  
                                    v-model="savingStartDate"
                                    :placeholder="$t('Start Date')"
                                    :label="$t('Savings Start Date')"
                                    class="app-date-time-picker"
                                    clearable
                                    clear-icon="mdi-close"
                                  />
                                </VCol>
                                <VCol cols="12" md="2" sm="4">
                                  <AppDateTimePicker  
                                    v-model="savingEndDate"
                                    :placeholder="$t('End Date')"
                                    :label="$t('Savings End Date')"
                                    class="app-date-time-picker"
                                    clearable
                                    clear-icon="mdi-close"
                                  />
                                </VCol>
                            </VRow>
                        </VCol>
                    </VRow>
                </VCardText>
                <!-- Line 2 -->
                <VCardText class="d-flex flex-wrap gap-5">
                    <VRow>
                        <VCol cols="12">
                            <VRow>
                                <VCol cols="12" md="2" sm="4">
                                    <AppSelect
                                        v-model="selectedStatus"
                                        :items="statusList"
                                        :label="$t('Status')"
                                        :placeholder="$t('Status')"
                                        clearable
                                        closable-chips
                                        chips
                                        multiple
                                        clear-icon="mdi-close"
                                        class="small"
                                    />
                                </VCol>
                                <VCol cols="12" md="2" sm="4">
                                    <AppAutocomplete
                                        v-model="selectedCategory"
                                        :items="categoryList"
                                        item-value="id"
                                        item-title="value"
                                        :label="$t('Categories')"
                                        :placeholder="$t('Categories')"
                                        clearable
                                        closable-chips
                                        chips
                                        multiple
                                        clear-icon="mdi-close"
                                        class="small"
                                        @update:model-value="populateSubCategory"
                                    />
                                </VCol>
                                <VCol cols="12" md="2" sm="4">
                                    <AppAutocomplete
                                        v-model="selectedSubCategory"
                                        :items="populatedSubCategoryList"
                                        item-value="id"
                                        item-title="value"
                                        :label="$t('Sub-Categories')"
                                        clearable
                                        closable-chips
                                        chips
                                        multiple
                                        clear-icon="mdi-close"
                                        class="small"
                                    />
                                </VCol>
                                <VCol cols="12" md="2" sm="4">
                                    <AppAutocomplete
                                        v-model="selectedLocation"
                                        :items="populatedLocationList"
                                        item-value="id"
                                        item-title="location_name"
                                        :label="$t('Locations')"
                                        :placeholder="$t('Locations')"
                                        clearable
                                        closable-chips
                                        chips
                                        multiple
                                        clear-icon="mdi-close"
                                        class="small"
                                        @update:model-value="populateDepartment"
                                    />
                                </VCol>
                                <VCol cols="12" md="2" sm="4">
                                    <AppAutocomplete
                                        v-model="selectedDepartment"
                                        :items="populatedDepartmentList"
                                        item-value="id"
                                        item-title="department_name"
                                        :label="$t('Departments')"
                                        :placeholder="$t('Departments')"
                                        clearable
                                        closable-chips
                                        chips
                                        multiple
                                        clear-icon="mdi-close"
                                        class="small"
                                        @update:model-value="populateLocation"
                                    />
                                </VCol>
                                <VCol cols="12" md="2" sm="4">
                                    <AppAutocomplete
                                        v-model="selectedCurrency"
                                        :items="currencyList"
                                        item-value="id"
                                        item-title="currency"
                                        :label="$t('Currency')"
                                        :placeholder="$t('Currency')"
                                        clearable
                                        closable-chips
                                        chips
                                        multiple
                                        clear-icon="mdi-close"
                                        class="small"
                                    />
                                </VCol>
                            </VRow>
                        </VCol>
                    </VRow>
                   
                </VCardText>
                <VCardText class="d-flex flex-wrap gap-5">
                  <VRow>
                    <VCol cols="12">
                      <VRow>

                        <VCol cols="12" md="2" sm="4">
                          <AppAutocomplete
                            v-model="selectedSavingType"
                            :items="savingTypeList"
                            item-value="id"
                            item-title="name"
                            :label="$t('Saving Type')"
                            :placeholder="$t('Saving Type')"
                            clearable
                            closable-chips
                            chips
                            multiple
                            clear-icon="mdi-close"
                            class="small"
                          />
                        </VCol>

                        <VCol cols="12" md="2" sm="4">
                            <AppAutocomplete
                                v-model="selectedCreatedBy"
                                :items="createdByList"
                                item-value="id"
                                item-title="name"
                                :label="$t('Created By')"
                                :placeholder="$t('Created By')"
                                clearable
                                closable-chips
                                chips
                                multiple
                                clear-icon="mdi-close"
                                class="small"
                                label="Created By"
                            />
                        </VCol>
                        
                        <VCol cols="12" md="2" sm="4">
                          <AppSelect
                            v-model="selectedArchiveStatus"
                            :items="archiveStatusList"
                            :label="$t('Archive Status')"
                            :placeholder="$t('Archive Status')"
                            class="small"
                            clearable
                            closable-chips
                            chips
                            multiple
                          />
                        </VCol>

                        <VCol cols="12" md="2" sm="4">
                          <AppSelect
                            v-model="selectedApprovalStatus"
                            :items="approvalStatusList"
                            :label="$t('Approval Status')"
                            :placeholder="$t('Approval Status')"
                            clearable
                            closable-chips
                            chips
                            multiple
                            clear-icon="mdi-close"
                            class="small"
                          />
                        </VCol>

                        <VCol cols="12" md="2" sm="4">

                          <AppAutocomplete
                            v-model="selectedSupplier"
                            :items="supplierList"
                            item-value="id"
                            item-title="name"
                            clearable
                            chips
                            closable-chips
                            multiple
                            clear-icon="mdi-close"
                            class="small"
                            label="Supplier"
                          />
                        </VCol>

                      <VCol cols="12" md="2" sm="12" class="d-flex flex-wrap gap-3">
                        <VSpacer />
                        <VBtn @click="clearFilters" variant="tonal" color="error">
                          {{ $t('Clear Filter') }}
                        </VBtn>
                      </VCol>
                      </VRow>
                    </VCol>
                  </VRow>
                </VCardText>
        
          <!-- End Filters -->
          <VDivider />

          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
              <AppSelect
                :model-value="options.itemsPerPage"
                :items="[
                  { value: 10, title: '10' },
                  { value: 20, title: '20' },
                  { value: 30, title: '30' },
                ]"
                style="width: 6.25rem"
                @update:model-value="options.itemsPerPage = parseInt($event, 10), options.page = 1"
              />
            <ColumnChooser v-if="isCustomFieldLoaded" :headers="savingsTableModifiedHeaders" @selected-headers="selectedHeaders"/>
            </div>
            <VSpacer />
            <div class="d-flex align-center flex-wrap gap-4" >
              <!-- 👉 Search  -->
              <div style="inline-size: 15rem">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>

              <!-- 👉 Export button  -->
              <VBtn
                v-if="$can('export', 'savings')"
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-screen-share"
                @click="exportSavings"
              >
                {{ $t('Export') }}
              </VBtn>

              <!-- 👉 Create Savings Record button -->
              <VBtn
                v-if="$can('create', 'savings')"
                prepend-icon="tabler-plus"
                :to="{ name: 'savings-create'}"
              >
                {{ $t('Create Savings Record') }}
              </VBtn>
            </div>
          </VCardText>
          <VDivider />

          <!-- SECTION datatable -->
            <VDataTableServer
              v-model:items-per-page="options.itemsPerPage"
              v-model:page="options.page"
              :items="savings"
              :items-length="totalSavings"
              :headers="SavingsTableHeaderTrans"
              :loading="tableLoader"
              :height="savings.length > 10 ? 50 * 12 - 1 : undefined"
              fixed-header
              class="main-table-dashboard text-no-wrap"
              :class="rowHighlightClass(savings[0])"
              @update:options="options = $event"
            >

              <!-- no data text -->
              <template #no-data>
                {{ $t('No data available') }}
              </template>

              <!-- name -->
              <template #item.name="{ item }">
                <div class="d-flex align-center">
                  <!-- :color="!item.raw?.supplier?.supplier_image?.file_path ? 'primary' : undefined" -->
                  <VAvatar
                    size="34"
                    color="primary"
                    class="me-3"
                  >

                  <!--
                    <VImg
                      v-if="item.raw?.supplier?.supplier_image?.file_path"
                      :src="item.raw?.supplier?.supplier_image?.file_path"
                      cover
                    />
                  -->
                    <span >{{ item.raw.id }}</span>
                  </VAvatar>

                  <div class="d-flex flex-column">
                    <h6 class="text-base">
                     <RouterLink
                        v-if="item.raw.is_draft == 0"
                        :to="{ name: 'savings-view-id', params: { id: item.raw.id }}"
                        class="font-weight-medium table-list-name-link"
                      >
                      {{ item.raw.name }} 
                      </RouterLink>
                      <RouterLink
                        v-else
                        :to="{ name: 'savings-create', query: { id: item.raw.id }}"
                        class="font-weight-medium table-list-name-link"
                      >
                      {{ item.raw.name }} 
                      </RouterLink>
                    </h6>
                  </div>
                </div>
              </template>

              <!-- status --> 
              <template #item.status="{ item }">
                <div class="d-flex align-center">
                  <VChip :color="evaluateStatus(item.raw).color">
                    {{ $t(evaluateStatus(item.raw).title)}}
                  </VChip>
                  
                </div>
              </template>

              <!-- saving_type --> 
              <template #item.saving_type="{ item }">
                <div class="d-flex align-center">
                 {{ item.raw.saving_types?.name || '--'}}
                </div>
              </template>

              <!-- currency --> 
              <template #item.currency="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.currency?.currency || '--'}}
                </div>
              </template>

              <!-- category --> 
              <template #item.category="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.category?.value || '--'}}
                </div>
              </template>

              <!-- subCategory --> 
              <template #item.subCategory="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.subcategory?.value || '--'}}
                </div>
              </template>

              <!-- location --> 
              <template #item.location="{ item }">
                <div class="d-flex align-center">
                  {{ [...new Set(item.raw?.get_contract_location_department?.map(item => item?.get_dpartment_location?.locations?.location_name))].join(", ") || '--'}}
                </div>
              </template>

              <!-- department --> 
              <template #item.department="{ item }">
                <div class="d-flex align-center">
                  {{ [...new Set(item.raw?.get_contract_location_department?.map(item => item?.get_dpartment_location?.departments?.department_name))].join(", ")|| '--'}}
                </div>
              </template>

              <!-- supplier_name --> 
              <template #item.supplier_name="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.supplier?.name || '--'}}
                </div>
              </template>

              <!-- contract_name --> 
              <template #item.contract_name="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.contract?.title || '--'}}
                </div>
              </template>

              <!-- relation_type --> 
              <template #item.relation_type="{ item }">
                <div class="d-flex align-center">
                  {{ getRelationType(item.raw.parent_child?.relation_type)}}
                </div>
              </template>

              <!-- parent_child --> 
              <template #item.parent_child="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw.parent_child?.saving_record?.name || '--'}}
                </div>
              </template>

              <!-- total_base_line_spend --> 
              <template #item.total_base_line_spend="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.total_base_line_spend && item.raw?.currency?.currency_symbol +''+  savingCurrencyFormatter(item.raw?.total_base_line_spend)  || '--'}}
                </div>
              </template>

              <!-- total_project_saving --> 
              <template #item.total_project_saving="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.total_project_saving && item.raw?.currency?.currency_symbol +''+ savingCurrencyFormatter(item.raw?.total_project_saving) || '--'}}
                </div>
              </template>

              <!-- total_project_saving_perc --> 
              <template #item.total_project_saving_perc="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.total_project_saving_perc && item.raw?.total_project_saving_perc?.toFixed(2) + '%'  || '--'}}
                </div>
              </template>

              <!-- total_realised_saving_base_line --> 
              <template #item.total_realised_saving_base_line="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.total_realised_saving_base_line && item.raw?.currency?.currency_symbol + '' + savingCurrencyFormatter(item.raw?.total_realised_saving_base_line) || '--'}}
                </div>
              </template>

              <!-- total_realised_saving --> 
              <template #item.total_realised_saving="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.total_realised_saving && item.raw?.currency?.currency_symbol + '' + savingCurrencyFormatter(item.raw?.total_realised_saving) || '--'}}
                </div>
              </template>

              <!-- total_realised_saving_perc --> 
              <template #item.total_realised_saving_perc="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.total_realised_saving_perc && item.raw?.total_realised_saving_perc?.toFixed(2) + '%' || '--'}}
                </div>
              </template>

              <!-- end_date --> 
              <template #item.end_date="{ item }">
                <div class="d-flex align-center">
                  {{ formatDate(item.raw.end_date) }}
                </div>
              </template>

              <!-- created_at --> 
              <template #item.created_at="{ item }">
                <div class="d-flex align-center">
                  {{ formatDate(item.raw.created_at) }}
                </div>
              </template>

              <!-- created_by --> 
              <template #item.created_by="{ item }">
                <div class="d-flex align-center">
                  {{ item.raw?.user?.name || '--'}}
                </div>
              </template>

              <template v-for="(header) in customFieldHeader" v-slot:[`item.${customHeaderKey(header)}`]="{ item }">
                <div class="d-flex align-center" :key="`${customHeaderKey(header)}`">
                  {{ getFormattedCustomFieldValue(item.raw.custom_fields, header) }}
                </div>
              </template>

              <!-- Actions -->
              <template #item.actions="{ item }">
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  color="medium-emphasis"
                >
                  <VIcon
                    size="24"
                    icon="tabler-dots-vertical"
                  />

                  <VMenu activator="parent">
                    <VList>
                      <VListItem 
                        v-if="item.raw.is_draft == 0"
                        link :to="{ name: 'savings-view-id', params: { id: item.raw.id }}"
                      >
                        <template #prepend>
                          <VIcon icon="tabler-eye" />
                        </template>

                        <VListItemTitle>{{ $t('View') }}</VListItemTitle>
                      </VListItem>

                      <VListItem 
                        v-else
                        link :to="{ name: 'savings-create', query: { id: item.raw.id }}"
                      >
                        <template #prepend>
                          <VIcon icon="tabler-eye" />
                        </template>

                        <VListItemTitle>{{ $t('View') }}</VListItemTitle>
                      </VListItem>

                      <VListItem link v-if="$can('archive', 'savings') && item.raw.user.id == loggedInUser" @click="showArchiveDialog(item.raw)">
                        <template #prepend>
                          <VIcon :icon="item.raw.archive == 1 ? 'tabler-archive-off' : 'tabler-archive' " />
                        </template>
                        <VListItemTitle>{{ $t(item.raw.archive == 1 ? 'Unarchive' : 'Archive')}}</VListItemTitle>
                      </VListItem>

                    </VList>
                  </VMenu>
                </VBtn>
              </template>

              <!-- pagination -->
              <template #bottom>
                <VDivider />
                <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                  <p class="text-sm text-disabled mb-0">
                    {{ paginationMeta(options, totalSavings) }}
                  </p>

                  <VPagination
                    v-model="options.page"
                    :length="Math.ceil(totalSavings / options.itemsPerPage)"
                    :total-visible="$vuetify.display.smAndDown ? 3 : 5"
                  >
                    <template #prev="slotProps">
                      <VBtn
                        variant="tonal"
                        color="default"
                        v-bind="slotProps"
                        :icon="false"
                      >
                        {{ $t('Previous') }}
                      </VBtn>
                    </template>

                    <template #next="slotProps">
                      <VBtn
                        variant="tonal"
                        color="default"
                        v-bind="slotProps"
                        :icon="false"
                      >
                        {{ $t('Next') }}
                      </VBtn>
                    </template>
                  </VPagination>
                </div>
              </template>
            </VDataTableServer>
          <!-- End datatable SECTION -->
        </VCard>
      </VCol>
    </VRow>

    <!-- 👉 confirmation dialog for archive saving record-->
    <SimpleConfirmDialog
      v-model:isDialogVisible="archiveDialog"
      @click-ok="toggleArchiveSavings"
      :title="archiveDialogTitle"
      :sub-title="archiveDialogSubTitle"
      ok-btn-text="Yes"
      cancel-btn-text="No"
    />
  </section>
</template>

<style lang="scss">

</style>
<route lang="yaml">
  meta:
    action: read
    subject: savings
</route>
