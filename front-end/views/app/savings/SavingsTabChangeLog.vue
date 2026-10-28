<script setup>
import { VDataTableServer, VDataTable } from "vuetify/labs/VDataTable";
import { changeLogHeader } from "@core/utils/tableHeaders";
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { useSnackBarStore } from "@core/utils/snackBar";
import { paginationMeta } from "@/plugins/dashboards/utils";
import { avatarText } from '@core/utils/formatters';
import { formatDate } from "@/plugins/date/dateUtils";
import useEmitter from "@/plugins/emitter/useEmitter";
import { useI18n } from "vue-i18n";

const I18n = useI18n();
const props = defineProps({
  savingId: {
    type: Number,
    required: true,
  },
});

const emitter = useEmitter()
const savingStore = useSavingsStore()
const snackBarStore = useSnackBarStore();
const selectedLogFilter = ref(null);
const changedLogFilters = ref([]);

const changeLogs = ref([]);

const searchQuery = ref();
const tableLoader = ref(false);
const milestoneModule = ref();

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
});

onMounted(() => {
  fetchSavingsChangeLogs(); // fetch Savings log changes
  fetchSavingsLogFilter(); // fetch Savings log filter


  emitter.on("refreshSavingsChangeLogs", () => {
    fetchSavingsChangeLogs(); // fetch Savings log changes
  });
})


watchDebounced(() => ({
  searchQuery: searchQuery.value,
  logFilter: selectedLogFilter.value,
}),
  (v) => {
    fetchSavingsChangeLogs();  // fetch contract log
  },
  { debounce: 400 },
)

const changeLogHeaderTemp = ref(structuredClone(toRaw(changeLogHeader.filter(item => item.visible))));

const changeLogHeaderTrans = computed(() => {
  const tempHeader = [];

  changeLogHeaderTemp.value.forEach(item => {
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

// fetch all Savings change logs
const fetchSavingsChangeLogs = () => {
  const params = {
    id: props.savingId,
    search: searchQuery.value,
    logFilter: selectedLogFilter.value,
  }
  savingStore.fetchSavingsChangeLogs(params)
  .then(response => {
    const { logs } = response.data;
    milestoneModule.value = response.data?.submodule_id?.id;
    changeLogs.value = Object.values(logs);
  })
  .catch(error => {
    console.error(error)
  })
  .finally(() =>{});
}

// fetch Savings change logs filter
const fetchSavingsLogFilter = () => {
  savingStore.fetchSavingsLogFilter()
  .then(response => {
    changedLogFilters.value = response.data;
  })
  .catch(error => {
    console.error(error)
  })
  .finally(() =>{});

};

const logFilterList = computed(() => {
  return changedLogFilters.value?.map(item => {
    return {
      ...item,
      submodule_name:  I18n.t(item.submodule_name),
    }
  });
});
</script>

<template>
 <VRow>
    <VCol cols="12">
      <VCard :title="$t('Change Log')">
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
          </div>
          
          <VSpacer />
          <div class="me-3 d-flex gap-3">
            <AppSelect
              v-model="selectedLogFilter"
              :items="logFilterList"
              item-title="submodule_name"
              item-value="id"
              style="width: 16.25rem"
              clearable
              label="Filter Log By Tab"
            />
          </div>

          <div class="d-flex align-center flex-wrap gap-4" >
            <!-- 👉 Search  -->
            <div style="inline-size: 15rem">
              <AppTextField
                v-model="searchQuery"
                :placeholder="$t('Search')"
                density="compact"
                clearable
                @update:model-value="options.page = 1"
              />
            </div>

          </div>

        </VCardText>
        <VDivider />

        <!-- SECTION Datatable --> 
        
        <VDataTable
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          :items="changeLogs"
          :headers="changeLogHeaderTrans"
          class="text-wrap pb-5"
          expand-on-click
          @update:options="options = $event"
        >

          <!-- no data text -->
          <template #no-data>
            {{ $t('No data available') }}
          </template>

          <!-- Expanded Row Data -->
          <template #expanded-row="slotProps">
            <tr class="v-data-table__tr">
              <!-- to skip the first icon column -->
              <td :colspan="1">

              </td>
              <td :colspan="changeLogHeader.length - 1">
                <div class="print-row">
                  <div class="mt-4" v-for="(logsTab, index) in slotProps.item.raw.logs" :key="index">

                    <h6 class="font-weight-bolder text-h4 text-capitalize mt-8">
                      {{ $t('Tab') }}: {{$t(index)}}
                    </h6>

                    <div v-if="logsTab.sub_module_id == milestoneModule" v-for="(milestoneLog, key) in logsTab.logs" :key="key">
                      
                      <p class="my-3 mb-1 mt-4">
                        <span class="font-weight-bolder text-h5 text-capitalize">
                          {{ $t('Milestone') }}: 
                        </span>
                        <span class="font-weight-medium text-h6">
                          {{key}}
                        </span>

                        <p class="mb-0 mx-10" v-for="(log, i) in milestoneLog.logs" :key="i">
                          <span class="font-weight-bolder text-h6 text-capitalize">
                            {{ $t(log?.column_name) }}: 
                          </span>

                          <span> {{ $t('Changed from') }}
                            <span class="font-weight-bolder text-h6">
                              {{ log?.column_name?.includes('date') ? formatDate(log.old_value) : log.old_value?.toString() || "--" }}
                            </span>
                            {{ $t('to') }} 
                            <span class="font-weight-bolder text-h6">
                              {{ log?.column_name?.includes('date') ? formatDate(log.new_value) : log.new_value?.toString() || "--" }}
                            </span>
                          </span>
                        </p>

                      </p>

                    </div>

                    <div v-else v-for="(log, ind) in logsTab.logs" :key="ind">

                      <p class="my-3">
                        <span class="font-weight-bolder text-h6 text-capitalize">
                          {{ $t(log?.column_name) }}: 
                        </span>

                        <span v-if="index == 'Saving Owner'">  
                          <span class="font-weight-bolder text-h6">
                            {{ log.old_value?.toString()}}
                          </span>
                          <span class="font-weight-bolder text-h6">
                            {{ log.new_value?.toString() }}
                          </span>
                        </span>

                        <span v-else> {{ $t('Changed from') }} 
                          <span class="font-weight-bolder text-h6">
                            {{ log?.column_name?.includes('date') ? formatDate(log.old_value) : log.old_value?.toString() || "--" }}
                          </span>
                          {{ $t('to') }} 
                          <span class="font-weight-bolder text-h6">
                            {{ log?.column_name?.includes('date') ? formatDate(log.new_value) : log.new_value?.toString() || "--" }}
                          </span>
                        </span>
                      </p>

                    </div>

                  </div>
                </div>
              </td>
            </tr>
          </template>

          <!-- Name -->
          <template #item.name="{ item }">
            <div class="d-flex align-center">
              <VAvatar
                size="34"
                :color="!item.raw.user?.user_image?.file_path ? 'primary' : undefined"
                class="me-3"
              > 
                <VImg
                  v-if="item.raw.user?.user_image?.file_path"
                  :src="item.raw.user?.user_image?.file_path"
                  cover
                />
                <span v-else>{{ avatarText(item.raw.user?.name) }}</span>
              </VAvatar>

              <div class="d-flex flex-column">
                <h6 class="text-base text-capitalize">
                    {{ item.raw?.user.name }}
                </h6>
              </div>
            </div>
          </template>

          <!-- change log date -->
          <template #item.created_at="{ item }">
            <p class="font-weight-medium mb-0">
              {{ formatDate(item.raw.created_at) }}
            </p>
          </template>

          <!-- pagination -->
          <template #bottom>
            <VDivider />
            <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
              <p class="text-sm text-disabled mb-0">
                {{ paginationMeta(options, changeLogs.length) }}
              </p>

              <VPagination
                v-model="options.page"
                :length="Math.ceil(changeLogs.length / options.itemsPerPage)"
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
        <!-- !SECTION -->
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>


</style>
