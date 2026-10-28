<script setup>
import { useSavingsStore } from '@/router/stores/useSavingsStore'
import { useSnackBarStore } from "@core/utils/snackBar";
import { paginationMeta } from "@/plugins/dashboards/utils";
import { VDataTable } from 'vuetify/labs/VDataTable'
import { autoBuilderMilestoneHeader } from "@core/utils/tableHeaders";
import { formatDate } from "@/plugins/date/dateUtils";
import { useI18n } from "vue-i18n";

const I18n = useI18n();
const emit = defineEmits([
  'prev',
  'next',
  'savedMilestone',
])

const props = defineProps({
  milestoneData: {
    type: Array,
    required: true,
  },
  type: {
    type: Boolean,
    required: true,
  }
})

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
});

const milestoneHeader = ref(structuredClone(toRaw(autoBuilderMilestoneHeader.filter(item => item.visible))));

const milestoneHeaderTrans = computed(() => {
  const tempHeader = [];

  milestoneHeader.value.forEach(item => {
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


const savingsStore = useSavingsStore()
const snackBarStore = useSnackBarStore();
const showAlertMessage = ref(false);
const selectedItem = ref({});
const showEditDialog = ref(false);

const editItem = (item) => {
  selectedItem.value = item;
  showEditDialog.value = true;
}

const previous = () => {
  emit('prev', 1)
}

const editMilestoneResponse = (row) => {
  const rawId = row.number;
  const index = props.milestoneData.map(e => e.number).indexOf(rawId)
  props.milestoneData[index] = row;
}

const submitMilestone = () => {


  const postData = {
    milestone: props.milestoneData,
    saving_id: savingsStore.recentSavingsId,
    time_period: savingsStore.timePeriod,
    milestone_frequency: savingsStore.frequency,
    milestone_type: savingsStore.milestoneType,
  };

  savingsStore.createMilestone(postData)
  .then((response) => {

    emit('savedMilestone', response.data.milestones);
    snackBarStore.message = response.data.message;
    snackBarStore.type = 'success';

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
  });

}

const isMilestoneValid = computed(() => {

  let isValid = true;

  props.milestoneData.forEach(milestone => {

    if(!milestone.name || !milestone.start_date || !milestone.end_date)
      isValid = false;

  });

  showAlertMessage.value = !isValid;
  return isValid;
});

</script>

<template>
  <VCardItem class="text-start">
    <VCardTitle class="text-h5">
      {{ $t(props.type ? 'Automated Milestone Builder' : 'Custom Milestone Builder') }}
    </VCardTitle>
  </VCardItem>

  <VDivider />

  <VCardText class="pt-2">
    <VRow>
      <VCol cols="12">
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

        </VCardText>

        <VDivider />

        <!-- 👉 Datatable  -->
        <VDataTable
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          :headers="milestoneHeaderTrans"
          :items="props.milestoneData"
          :height="props.milestoneData.length > 10 ? 50 * 12 - 1 : undefined"
          fixed-header
          @update:options="options = $event"
        >

          <!-- no data text -->
          <template #no-data>
            {{ $t('No data available') }}
          </template>

          <!-- number -->
          <template #item.number="{ item }">
            <div class="d-flex align-center">
              <!-- avatar -->
              <VAvatar
                  size="34"
                  :variant="'tonal'"
                  :color="undefined"
                  class="me-3"
              >
                <span>{{ item.raw.number }}</span>
              </VAvatar>
            </div>
          </template>

          <!--name-->
          <template #item.name="{ item }">
            <div class="d-flex align-center">
              {{ item.raw.name || '--' }}
            </div>
          </template>

          <!--start_date-->
          <template #item.start_date="{ item }">
            <div class="d-flex align-center">
              {{ formatDate(item.raw.start_date) }}
            </div>
          </template>

          <!--end_date-->
          <template #item.end_date="{ item }">
            <div class="d-flex align-center">
              {{ formatDate(item.raw.end_date) }}
            </div>
          </template>

          <!--base_line_spend-->
          <template #item.base_line_spend="{ item }">
            <div class="d-flex align-center">
              {{ savingsStore.currencyIcon + item.raw.base_line_spend }}
            </div>
          </template>

          <!--project_cost_reduction-->
          <template #item.project_cost_reduction="{ item }">
            <div class="d-flex align-center">
              {{ savingsStore.currencyIcon + item.raw.project_cost_reduction }}
            </div>
          </template>

          <!--project_cost_avoidance-->
          <template #item.project_cost_avoidance="{ item }">
            <div class="d-flex align-center">
              {{ savingsStore.currencyIcon + item.raw.project_cost_avoidance }}
            </div>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <div class="d-flex">
              <VBtn
                variant="tonal"
                color="warning"
                class="me-4"
                @click="editItem(item.raw)"
                size="small"
              >
                {{ $t('Edit') }}
              </VBtn>
            </div>
          </template>

          <!-- pagination -->
          <template #bottom>
            <VDivider />
            <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
              <p class="text-sm text-disabled mb-0">
                {{ paginationMeta(options, props.milestoneData.length) }}
              </p>

              <VPagination
                v-model="options.page"
                :length="Math.ceil(props.milestoneData.length / options.itemsPerPage)"
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

      </VCol>

      <VCol cols="12">
        <VAlert
          v-model="showAlertMessage"
          color="error"
          variant="tonal"
        >
          {{ $t('To proceed further, kindly provide the names, start dates, and end dates for all milestones') }}
        </VAlert>
      </VCol>

      <VDivider />

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
          :disabled="!isMilestoneValid"
          @click="submitMilestone"
        >
          {{ $t('Next') }}

          <VIcon
            icon="tabler-arrow-right"
            end
            class="flip-in-rtl"
          />
        </VBtn>

      </VCol>
    </VRow>
  </VCardText>

  <!-- Edit item -->
  <editMilestoneRowDialog 
    v-model:isDialogVisible="showEditDialog"
    :item="selectedItem"
    @response="editMilestoneResponse"
  />

</template>

<style lang="scss">

</style>
