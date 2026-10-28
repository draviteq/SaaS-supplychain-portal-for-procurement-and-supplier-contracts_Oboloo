<script setup>
import { useSavingsStore } from '@/router/stores/useSavingsStore'
import { useSnackBarStore } from "@core/utils/snackBar";
import { requiredValidator } from "@validators";
import { formatDate } from "@/plugins/date/dateUtils";
import useEmitter from "@/plugins/emitter/useEmitter"

const emitter = useEmitter()
const savingsStore = useSavingsStore()
const snackBarStore = useSnackBarStore();
const router = useRouter()
const addSavingsLoader = ref(false);
const showAlertMessage = ref(false);



const emit = defineEmits([
  'prev',
  'next',
])

const props = defineProps({
  type: {
    type: Boolean,
    required: true,
  },
  milestoneData: {
    type: Array,
    required: true,
  },
})

const previous = () => {
  emit('prev', 1);
}

const createSavingsRecord = () => {
  addSavingsLoader.value = true;
  const postData = {
    id: savingsStore.recentSavingsId,
    start_date: formatDateToSave(savingsStartDate.value),
    end_date: formatDateToSave(savingsEndDate.value),
    approver_id: selectedSavingsApprover.value,
    needs_approval: isSavingsNeedApproval.value ? '1' : '0',
  }
  savingsStore.activateSavingsRecord(postData)
  .then((response) => {
    snackBarStore.message = response.data.message;
    snackBarStore.type = 'success';
    savingsStore.$reset();
    emitter.emit('refreshSavingsDashboard');
    
    router.replace('/savings/list'); // back to savings listing
  })
  .catch((error) => {
    const { message, errors: errorData} = error.response.data
    snackBarStore.error = errorData;
    snackBarStore.message = message;
    snackBarStore.type = 'error';
  })
  .finally(() => {
    addSavingsLoader.value = false;
    snackBarStore.snackbar = true;
  });
}

const totalBaselineSpend = computed(() => {
  return props.milestoneData.map(item => item.base_line_spend).reduce((accumulator, currentValue) => +accumulator + +currentValue, 0).toFixed(2);
})

const totalCostReduction = computed(() =>{
  return props.milestoneData.map(item => item.project_cost_reduction).reduce((accumulator, currentValue) => +accumulator + +currentValue, 0).toFixed(2);
});

const totalCostAvoidance = computed(() =>{
  return props.milestoneData.map(item => item.project_cost_avoidance).reduce((accumulator, currentValue) => +accumulator + +currentValue, 0).toFixed(2);
});

const totalSavings = computed(() =>{
  return (+totalCostAvoidance.value + +totalCostReduction.value).toFixed(2) || 0;
});

const totalSavingsPercentage = computed(() =>{
  return totalBaselineSpend.value != 0 ? ((totalSavings.value / totalBaselineSpend.value) * 100).toFixed(2) : 0;
});

const savingsStartDate = computed(() =>{
  const startDates = props.milestoneData.map(item => new Date(item.start_date));
  return new Date(Math.min(...startDates));
});

const savingsEndDate = computed(() =>{
  const endDates = props.milestoneData.map(item => new Date(item.end_date));
  return new Date(Math.max(...endDates));
});


// calculate the exact date
const formatDateToSave = (dateString) => {

  const date = new Date(dateString);

  // Check if the date is valid
  if (isNaN(date.getTime())) {
    return null;
  }

  const day = date.getDate();
  const month = date.getMonth() + 1;
  const year = date.getFullYear();

  return `${year}-${month}-${day}`;

};
const reloadTable = ref(false);

 const selectedSavingsApprover = ref(null);
 const isSavingsNeedApproval = ref(false);
 watch(isSavingsNeedApproval, () =>{
  if(!isSavingsNeedApproval.value)
    reloadTable.value = true;

  setTimeout(() => {
    reloadTable.value = false;
  }, 0);
})

// initial function
onMounted(() => {
  fetchSavingFilters() // fetch the filters options
});
const loggedInUserId = useLocalStorage('loginUserId')

const activeUsers = ref([]);
const fetchSavingFilters = () => {
  savingsStore.fetchActiveSavingFilters()
  .then((response) => {
    activeUsers.value = response.data.approverUsers?.filter(item => item.id != loggedInUserId.value);

  }).catch((error) => {
    const { message, errors: errorData} = error.response.data
    snackBarStore.error = errorData;
    snackBarStore.message = message;
    snackBarStore.type = 'error';

    snackBarStore.snackbar = true;
  });
}

const approveIsValid = computed(() => {
let isValid = true;
  if (isSavingsNeedApproval.value && !selectedSavingsApprover.value) {
      isValid = false;
  }
  showAlertMessage.value = !isValid;
  return isValid;
})

</script>

<template>
  <VCardItem class="text-start">
    <VCardTitle class="text-h5">
      {{ $t('Savings Summary') }}
    </VCardTitle>
  </VCardItem>

  <VDivider />

  <VCardText class="pt-2">

    <h6 class="text-h4 mb-5">
      {{ $t('Please confirm that you are happy with the below saving') }}
    </h6>

    <h6 class="text-h5 mb-5 text-underline">
      {{ $t('Financials') }}
    </h6>

    <table class="mb-8">
      <tr>
        <td class="pe-6 pb-0">
          {{ $t('Total Baseline Spend') }}:
        </td>
        <td class="pb-0">
          <span class="font-weight-medium">
            {{ savingsStore.currencyIcon }}{{ totalBaselineSpend }} 
          </span>
        </td>
      </tr>
      <tr>
        <td class="pe-6 pb-0">
          {{ $t('Projected Cost Reduction (across all milestones)') }}:
        </td>
        <td class="pb-0">
          <span class="font-weight-medium">
            {{ savingsStore.currencyIcon }}{{ totalCostReduction }} 
          </span>
        </td>
      </tr>
      <tr>
        <td class="pe-6 pb-0">
          {{ $t('Projected Cost Avoidance (across all milestones)') }}:
        </td>
        <td class="pb-0">
          <span class="font-weight-medium">
            {{ savingsStore.currencyIcon }}{{ totalCostAvoidance }}  
          </span>
        </td>
      </tr>
      <tr>
        <td class="pe-6 pb-0">
          {{ $t('Total Projected Savings') }}:
        </td>
        <td class="pb-0">
          <span class="font-weight-medium">
            {{ savingsStore.currencyIcon }}{{ totalSavings }} 
          </span>
        </td>
      </tr>
      <tr>
        <td class="pe-6 pb-0">
          {{ $t('Total Projected Savings (%)') }}:
        </td>
        <td class="pb-0">
          <span class="font-weight-medium">
            {{ totalSavingsPercentage }} %
          </span>
        </td>
      </tr>
    </table>


    <h6 class="text-h5 mt-4 mb-5 text-underline">
      {{ $t('Start and End Date') }}
    </h6>


    <table class="mb-8">
      <tr>
        <td class="pe-6 pb-0">
          {{ $t('Savings Start Date') }}:
        </td>
        <td class="pb-0">
          <span class="font-weight-medium">
            {{ formatDate(savingsStartDate) }}
          </span>
        </td>
      </tr>
      <tr>
        <td class="pe-6 pb-0">
          {{ $t('Savings End Date') }}:
        </td>
        <td class="pb-0">
          <span class="font-weight-medium">
            {{ formatDate(savingsEndDate) }}
          </span>
        </td>
      </tr>
    </table>
    <VDivider />

    <VCol cols="12">
      <VSwitch
        v-model="isSavingsNeedApproval"
        density="compact"
        :label="$t('Does this saving need to be approved?')"
      />
    </VCol>
    
    <VCol cols="12" md="4" v-if="isSavingsNeedApproval">
      <AppAutocomplete
        v-model="selectedSavingsApprover"
        :items="activeUsers"
        item-title="name"
        item-value="id"
        label="Approver"
        clearable
        clear-icon="mdi-close"
        class="required"
        :rules="[requiredValidator]"
      />
    </VCol>
   
    <VDivider />

    <VRow class="mt-8">
      <VCol cols="12" class="d-flex gap-4">
        <VBtn
          @click="previous"
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
          :disabled="!approveIsValid"
          @click="createSavingsRecord"
        >
          {{ $t('Create Saving') }}
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
  </VCardText>
</template>

<style lang="scss">

</style>
