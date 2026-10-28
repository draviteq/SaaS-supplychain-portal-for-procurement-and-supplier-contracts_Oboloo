<script setup>
import { VDataTable } from 'vuetify/labs/VDataTable'
import { formatDate } from "@/plugins/date/dateUtils";
import { ref} from "vue";
import { useSnackBarStore } from "@core/utils/snackBar";
import { savingMilestone } from "@core/utils/tableHeaders";
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import ApexChartColumnMarkerBar from "@/views/charts/apex-chart/ApexChartColumnMarkerBar.vue"
import { chartJsCustomColors } from "@core/utils/chartJsCustomColors";
import { kFormatter } from "@core/utils/formatters";
import { scrollTop, paginationMeta } from "@/plugins/dashboards/utils";
import { getFormattedCustomFieldValue } from "@/plugins/common";
import { useI18n } from "vue-i18n";

const I18n = useI18n();

const snackBarStore = useSnackBarStore();
const savingStore = useSavingsStore();
const loggedInUser = localStorage.getItem("loginUserId"); // log in user
const savingMilestones = ref([]);
const props = defineProps({
  savingData: {
    type: Object,
    required: true,
  },
});
const isEditMilestonesDialogVisible = ref(false)
const isMarkAsCompleteDialogVisible = ref(false)
const isAddDocumentDialogVisible = ref(false)
const totalPage = ref(0);
const searchQuery = ref('')
const emit = defineEmits(["response"]);
const totalSavingMilestones = ref(0)
const isCustomFieldLoaded = ref(true)
const customFields = ref([]);

const options = ref({
  page: 1,
  itemsPerPage: 10,
  offset: 0,
})

onMounted(() => {
  fetchSavingMilestone();
});

const isChartLoaded = ref(false);

const fetchSavingMilestone = () => { 
  isChartLoaded.value = false;
  isCustomFieldLoaded.value = false;
  savingStore.fetchSavingMilestone({
    saving_id: props.savingData.id,
    options: options.value,
    search: searchQuery.value,
  })
  .then((response) => {
    savingMilestones.value = response.data.milestoneData;
    totalSavingMilestones.value = response.data.total;
    totalPage.value = response.data.totalPage;
    customFields.value = response.data.custom_fields;

    populateGraphDate(savingMilestones.value); // calculate the date for the graph

  })
  .catch((error) => {
    console.log(error);
  })
  .finally(() => {
    isChartLoaded.value = true;
    isCustomFieldLoaded.value = true;
  });
};

//---------------------------------Add Form Document---------------------------------------//

const openAddMilestonesDialog = () => {
  scrollTop()
  .then(() => {
    isAddDocumentDialogVisible.value = true;
  });
}

//----------------------------------- Download Document -----------------------------------//

const getFileName = (url) => {
  const parts = url.split("/");
  return parts[parts.length - 1];
};

const addMilestone = () => {
  emit('response');
  fetchSavingMilestone();
}


const savingMilestonTableModifiedHeaders = computed(() => {
  const tempHeader = [...savingMilestone];
  
  customFields.value.forEach(item => {
    tempHeader.push({
      title: item.field_name,
      key: customHeaderKey(item),
      sortable: false,
      visible: false,
      fixed: false
    });
  })

  // Filter out the actions object
  const filteredArr = tempHeader.filter(item => item.key != "action");

  // Find the actions object
  const actionsObj = tempHeader.find(item => item.key == "action");

  // Append the actions object at the end if it exists
  if (actionsObj) {
    filteredArr.push(actionsObj);
  }

  return filteredArr;
});

const savingMileston = ref(structuredClone(toRaw(savingMilestonTableModifiedHeaders.value.filter(item => item.visible))));

const savingMilestonTrans = computed(() => {
  const tempHeader = [];

  savingMileston.value.forEach(item => {
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
};

const selectedHeaders = (headers) => {
  savingMileston.value = headers;

  // this is to refresh the table listing
  const temp = savingMilestones.value;
  savingMilestones.value = [];
  savingMilestones.value = temp;
}
const evaluateStatus = (status) => {

  if(status == 1) {
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
  else{
    return {
      color: 'error',
      title: 'Cancelled',
    }
  }
}
const oneMilestoneData = ref({});
const openEditMilestonesDialogVisible = (id) => {
    savingStore
        .fetchMilestoneTabOneData({
            id:id
        })
        .then((response) => {
            oneMilestoneData.value=response.data,
            window.scrollTo({
              top: 0,
              behavior: 'smooth',
            })
            setTimeout(() => {
              isEditMilestonesDialogVisible.value = true;
            }, 500);

        })
        .catch((error) => {
         
        })
        .finally(() => {
        });
 
}

const markAsCompleteMilestoneData = ref({});

const markAsComplete = (milestone) => {
  markAsCompleteMilestoneData.value = milestone
  
  scrollTop()
  .then(() => {
    isMarkAsCompleteDialogVisible.value = true;
  });
}

const savingsMilestoneGraph = ref({
  datasets: [],
  prefix: '',
  suffix: '',
});

const populateGraphDate = (data) => {
  const dataset = {
    name: 'Realised Savings',
    data: [],
  }
  for(const item of data) {
    let tempData = {}
    let goals = {
      name: 'Projected Savings',
      value: 0,
      strokeHeight: 5,
      strokeColor: chartJsCustomColors.warning,
    };
    tempData['x'] = item.name;
    tempData['y'] = item.total_realised_saving?.toFixed(2);
    goals['value'] = item.total_project_saving?.toFixed(2);
    tempData['goals'] = [goals];

    dataset['data'].push(tempData);
  }

  savingsMilestoneGraph.value['datasets'][0] = dataset;
  savingsMilestoneGraph.value['prefix'] = props.savingData.currencySymbol;
};

</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :title="$t('Saving Milestones')">
        <VCardText class="d-flex flex-wrap py-4 gap-4">

          <div class="me-3 d-flex gap-3">

            <AppSelect
              :model-value="options.itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 20, title: '20' },
                { value: 30, title: '30' },
              ]"
              style="width: 4.25rem"
              @update:model-value="options.itemsPerPage = parseInt($event, 10), options.page = 1"
            />
            <ColumnChooser v-if="isCustomFieldLoaded" :headers="savingMilestonTableModifiedHeaders" @selected-headers="selectedHeaders"/>
          </div>

          <!-- 👉 Search  -->
          <div style="inline-size: 160px;">
            <AppTextField
              v-model="searchQuery"
              :placeholder="$t('Search')"
              density="compact"
              clearable
              clear-icon="mdi-close"
              style="width: 12.25rem"
            />
          </div>
          <VSpacer />
          <div class="d-flex align-center flex-wrap gap-4" >
            <VBtn
              style="width: 14.25rem"
              prepend-icon="tabler-plus"
             @click="openAddMilestonesDialog"
             
            >
              {{ $t('Add Milestone') }}
            </VBtn>
          </div>
        </VCardText>
        <VDivider />

        <!-- VDataTable -->
        <VDataTable
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          :search="searchQuery"
          :headers="savingMilestonTrans"
          :items="savingMilestones"
          :height="savingMilestones.length > 10 ? 50 * 12 - 1 : undefined"
          fixed-header
          class="pb-5 text-no-wrap"
          @update:options="options = $event"
        >

          <!-- no data text -->
          <template #no-data>
            {{ $t('No data available') }}
          </template>

          <template #item.start_date="{ item }">
            {{formatDate(item.raw.start_date)}}
          </template>

          <template #item.created_by="{ item }">
            {{item.raw.user?.name || '--'}}
          </template>

          <template #item.end_date="{ item }">
            {{formatDate(item.raw.end_date)}}
          </template>
          
          <template #item.base_line_spend="{ item }">
            {{ item.raw.base_line_spend && props.savingData.currencySymbol + kFormatter(item.raw.base_line_spend) }}
          </template>

          <template #item.project_cost_reduction="{ item }">
            {{ item.raw.project_cost_reduction && props.savingData.currencySymbol  + kFormatter(item.raw.project_cost_reduction) }}
          </template>

          <template #item.project_cost_avoidance="{ item }">
            {{ item.raw.project_cost_avoidance && props.savingData.currencySymbol  + kFormatter(item.raw.project_cost_avoidance) }}
          </template>

          <template #item.total_project_saving="{ item }">
            {{ item.raw.total_project_saving && props.savingData.currencySymbol + kFormatter(item.raw.total_project_saving)}}
          </template>

          <template #item.total_project_saving_perc="{ item }">
            {{ item.raw.total_project_saving_perc && item.raw.total_project_saving_perc?.toFixed(2) + '%' }}
          </template>

          <template #item.realised_cost_reduction="{ item }">
            {{ item.raw.realised_cost_reduction && props.savingData.currencySymbol + kFormatter(item.raw.realised_cost_reduction)}}
          </template>

          <template #item.realised_cost_avoidance="{ item }">
            {{ item.raw.realised_cost_avoidance && props.savingData.currencySymbol  + kFormatter(item.raw.realised_cost_avoidance)}}
          </template>

          <template #item.total_realised_saving_base_line="{ item }">
            {{ item.raw.total_realised_saving_base_line && props.savingData.currencySymbol + kFormatter(item.raw.total_realised_saving_base_line) }}
          </template>

          <template #item.total_realised_saving="{ item }">
            {{ item.raw.total_realised_saving && props.savingData.currencySymbol + kFormatter(item.raw.total_realised_saving) }}
          </template>

          <template #item.total_realised_saving_perc="{ item }">
            {{ item.raw.total_realised_saving_perc && item.raw.total_realised_saving_perc?.toFixed(2) + '%' }}
          </template>

          <template #item.created_at="{ item }">
            {{formatDate(item.raw.created_at)}}
          </template>

          <!--
          <template #item.created_by="{ item }">
            {{formatDate(item.raw.created_at)}}
          </template>
          -->
          <template #item.status="{ item }">
            <div class="d-flex align-center">
              <VChip :color="evaluateStatus(item.raw?.status).color">
                {{ $t(evaluateStatus(item.raw?.status).title)}}
              </VChip>
              
            </div>
          </template>

          <template v-for="(header) in customFields" v-slot:[`item.${customHeaderKey(header)}`]="{ item }">
            <div class="d-flex align-center" :key="`${customHeaderKey(header)}`">
              {{ getFormattedCustomFieldValue(item.raw.custom_fields, header) }}
            </div>
          </template>

          <template #item.action="{ item }">
            <VBtn icon variant="text" size="small" color="medium-emphasis">
              <VIcon size="24" icon="tabler-dots-vertical" />
              <VMenu activator="parent">
                <VList>
                  <VListItem
                     v-if="$can('edit', 'savings')"
                      prepend-icon="tabler-edit"
                      @click="openEditMilestonesDialogVisible(item.raw.id)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-pencil" />
                    </template>
                    <VListItemTitle> {{ $t('Edit') }} </VListItemTitle>
                  </VListItem>

                  <VListItem
                      v-if="$can('edit', 'savings') && item.raw.status==1"
                      icon
                      variant="text"
                      size="small"
                      color="medium-emphasis"
                      @click="markAsComplete(item.raw)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-check" />
                    </template>
                    <VListItemTitle>{{ $t('Mark as Complete') }}</VListItemTitle>
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
                {{ paginationMeta(options, savingMilestones?.length) }}
              </p>

              <VPagination
                v-model="options.page"
                :length="Math.ceil(savingMilestones?.length / options.itemsPerPage)"
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

        </VDataTable>
      </VCard>
    </VCol>

    <!-- 👉 Savings Amount By Milestone & Savings Type Chart -->
    <VCol cols="12">
      <VCard :title="$t('Savings Amount By Milestone & Savings Type')">
        <VCardText>
          <ApexChartColumnMarkerBar v-if="isChartLoaded" :data="savingsMilestoneGraph" :height="400" />
        </VCardText>
      </VCard>
    </VCol> 

  </VRow>

  <AddSavingMilestonesTabDialog 
    v-model:is-dialog-visible="isAddDocumentDialogVisible"
    :saving-data="props.savingData"
    :custom-fields="customFields"
    @response="addMilestone"
  />

  <EditMilestonesTabDialog
    v-model:is-dialog-visible="isEditMilestonesDialogVisible"
    :milestone-data="oneMilestoneData"
    :currency-symbol="props.savingData.currencySymbol"
    :is-base-currency="props.savingData.isBaseCurrency"
    @response="addMilestone"
  />

  <EditMarkAsCompleteDialog
    v-model:is-dialog-visible="isMarkAsCompleteDialogVisible"
    :milestone-data="markAsCompleteMilestoneData"
    @response="addMilestone"
  />
</template>

<style lang="scss" >
.card-list {
  --v-card-list-gap: 0.75rem;
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
