<script setup>

import { ref, computed, watch,onMounted } from "vue";
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import SavingsSummaryBioPanel from '@/views/apps/savings/SavingsSummaryBioPanel.vue'
import SavingsTabFinancials from '@/views/apps/savings/SavingsTabFinancials.vue'
import SavingsTabMilestones from '@/views/apps/savings/SavingsTabMilestones.vue'
import SavingsTabAttachment from '@/views/apps/savings/SavingsTabAttachment.vue'
import SavingsTabOwners from '@/views/apps/savings/SavingsTabOwners.vue'
import SavingsTabNotification from '@/views/apps/savings/SavingsTabNotification.vue'
import SavingsTabChangeLog from '@/views/apps/savings/SavingsTabChangeLog.vue'
import useEmitter from "@/plugins/emitter/useEmitter"
import ApprovalLog from "@/views/apps/common/ApprovalLog.vue";
import CustomNotification from "@/views/apps/common/CustomNotification.vue";
import { NotificationCategory } from "@core/enums";

const savingStore = useSavingsStore()
const emitter = useEmitter()

const route = useRoute()
const savingsTab = ref(null)
const savingData = ref({})
const approvalLogs = ref([]);

const tabs = ref([
  {
    icon: 'tabler-report-money',
    title: 'Notifications & Notes',
  },
  {
    icon: 'tabler-report-money',
    title: 'Financials',
  },
  {
    icon: 'tabler-columns',
    title: 'Milestones',
  },
  {
    icon: 'tabler-file-stack',
    title: 'Attachments'
  },
  {
    icon: 'tabler-message-plus',
    title: 'Custom Notifications',
  },
  {
    icon: 'tabler-user',
    title: 'Savings Owners'
  },
  {
    icon: 'tabler-article',
    title: 'Change Log',
  },
  {
    icon: 'tabler-align-box-right-stretch',
    title: 'Approval Log', 
  },
])


const fetchSavings = (id) => {
  const params = {
    id: id,
  }

  savingStore.fetchOneSavings(params) 
  .then(response => {
    const saving = response.data.savingsData;
    savingData.value.allFilter = response.data.optionData;
    savingData.value.id = saving?.id;
    savingData.value.is_draft = saving?.is_draft;
    savingData.value.savingName = saving?.name || null;
    savingData.value.start_date = saving?.start_date || null;
    savingData.value.archive = saving?.archive || 0;
    savingData.value.status = saving?.status || 0;
    savingData.value.sub_category_id = saving?.category?.deleted_at ? null : saving?.subcategory?.deleted_at ? null : saving?.sub_category_id;
    savingData.value.subcategory = saving?.subcategory?.value || null;
    savingData.value.categoryId = saving?.category?.deleted_at ? null : saving?.category_id;
    savingData.value.supplier_id = saving?.supplier?.status == 1 ? saving?.supplier?.id : null;
    savingData.value.supplier_image = saving?.supplier?.supplier_image?.file_path || null;
    savingData.value.supplier = saving?.supplier?.name;
    savingData.value.total_base_line_spend = (saving?.total_base_line_spend || 0);
    savingData.value.realised_cost_avoidance = (saving?.realised_cost_avoidance || 0);
    savingData.value.realised_cost_reduction = (saving?.realised_cost_reduction || 0);
    savingData.value.project_cost_avoidance = (saving?.project_cost_avoidance || 0);
    savingData.value.project_cost_reduction = (saving?.project_cost_reduction || 0);
    savingData.value.total_project_saving = (saving?.total_project_saving || 0);
    savingData.value.total_project_saving_perc = (saving?.total_project_saving_perc || 0);
    savingData.value.total_realised_saving = (saving?.total_realised_saving || 0);
    savingData.value.total_realised_saving_base_line = (saving?.total_realised_saving_base_line || 0);
    savingData.value.total_realised_saving_perc = (saving?.total_realised_saving_perc || 0);
    savingData.value.updated_at = saving?.updated_at;
    savingData.value.userID = saving?.user?.id || null;
    savingData.value.name = saving?.user?.name || null;
    savingData.value.category = saving?.category?.value || null;
    savingData.value.contractId = saving?.contract_id || null;
    savingData.value.contract = saving?.contract?.title || null;
    savingData.value.created_at = saving?.created_at || null;
    savingData.value.created_by = saving?.user?.name || null;
    savingData.value.created_by_id = saving?.created_by || null;
    savingData.value.currency_id = saving?.currency_id; // saving?.currency?.stauts == 1 ? saving?.currency_id : null;
    savingData.value.currency = saving?.currency?.currency || null;
    savingData.value.currencySymbol = saving?.currency?.currency_symbol || null;
    savingData.value.deleted_at = saving?.deleted_at || null;
    savingData.value.description = saving?.description || null;
    savingData.value.saving_types_id = saving.saving_types?.deleted_at? null: saving.saving_types?.id;
    savingData.value.end_date = saving?.end_date || null;
    savingData.value.location = saving.get_contract_location_department.map(i => i?.get_dpartment_location?.locations?.location_name)?.join(", ")|| null;
    savingData.value.department = saving.get_contract_location_department.map(i => i?.get_dpartment_location?.departments?.department_name)?.join(", ")|| null;
    // savingData.value.locationIds = [...new Set(saving.get_contract_location_department?.filter(i => i?.get_dpartment_location?.deleted_at == null)?.map(i => i?.get_dpartment_location?.locations?.id))] || null;
    // savingData.value.departmentIds = [...new Set(saving.get_contract_location_department?.filter(i => i?.get_dpartment_location?.deleted_at == null)?.map(i => i?.get_dpartment_location?.departments?.id))] || null;
    savingData.value.locationIds = (saving.get_contract_location_department?.filter(i => i?.get_dpartment_location?.deleted_at == null)?.map(i => i?.get_dpartment_location?.locations?.id))[0] || null;
    savingData.value.departmentIds = (saving.get_contract_location_department?.filter(i => i?.get_dpartment_location?.deleted_at == null)?.map(i => i?.get_dpartment_location?.departments?.id))[0] || null;
    savingData.value.isBaseCurrency = saving?.base_currency == 1 ? true : false;
    savingData.value.saving_type = saving.saving_types?.name || null;

    savingData.value.additionalData = saving;
    approvalLogs.value = saving?.savingLogs || [];

    if (saving.needs_approval == 0 && approvalLogs.value.length == 0) {
        const approvalLogIndex = tabs.value.findIndex(tab => tab.title == 'Approval Log');
        if (approvalLogIndex != -1) {
          tabs.value.splice(approvalLogIndex, 1);
        }
      }
  })
}

onMounted(() => {
  fetchSavings(Number(route.params.id));
})

watchDebounced(
  () => route.params.id, 
  () => {
    if(route.params.id)
    fetchSavings(Number(route.params.id)); 
  },
  { debounce: 400 }
);

const editSavingsDetails = () => {
  fetchSavings(Number(route.params.id));

  emitter.emit("refreshSavingsChangeLogs"); // refresh the log tab
}

const alertMessage = computed(() => {
  if(savingData.value.additionalData?.approval_status == 0){
    return "This record is currently unapproved. Please wait for the approver to review and approve it.";  
  }

  if(savingData.value.additionalData?.approval_status == 2){
    return "This record is rejected by the approver. Click Send for approval button to apply for re-approval";  
  }

  return "This is an archived record. Click the Unarchive button to change the records archived status";
})

const showWarningMessage = computed(() => {

  return savingData.value.archive == 1 || savingData.value.additionalData?.approval_status == 0 || savingData.value.additionalData?.approval_status == 2;
});


</script>

<template>
 <VRow v-if="savingData" >

    <VCol cols="12" class="justify-start text-center" v-if="showWarningMessage">
      <VAlert
        variant="tonal"
        color="error"
      >
        {{ $t(alertMessage) }}
      </VAlert> 
    </VCol>

    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <SavingsSummaryBioPanel @edit-saving="editSavingsDetails" :saving-data="savingData"/>
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VTabs
        v-model="savingsTab"
        class="v-tabs-pill"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.icon"
        >
          <VIcon
            :size="18"
            :icon="tab.icon"
            class="me-1"
          />
          <span>{{ $t(tab.title) }}</span>
        </VTab>
      </VTabs>

      <VWindow
        v-model="savingsTab"
        class="mt-6 disable-tab-transition" 
        :touch="false"
      >
        <VWindowItem>
          <SavingsTabNotification />
        </VWindowItem>
        
        <VWindowItem>
          <SavingsTabFinancials :saving-data="savingData"/>
        </VWindowItem>

        <VWindowItem>
          <SavingsTabMilestones @response="editSavingsDetails" :saving-data="savingData"/>
        </VWindowItem>

        <VWindowItem>
          <SavingsTabAttachment :saving-data="savingData"/>
        </VWindowItem>

        <VWindowItem>
          <CustomNotification :category="NotificationCategory.SAVINGS"/>
        </VWindowItem>

        <VWindowItem>
          <SavingsTabOwners :saving-id="Number(route.params.id)"/>
        </VWindowItem>

        <VWindowItem>
          <SavingsTabChangeLog :saving-id="Number(route.params.id)" />
        </VWindowItem>

        <VWindowItem>
          <ApprovalLog :logs="approvalLogs"/>
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
</template>

<style lang="scss">

</style>

<route lang="yaml">
  meta:
    action: read
    subject: savings
    navActiveLink: savings-list
</route>
