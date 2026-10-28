<script setup>
import ApexChartStackedBar from "@/views/charts/apex-chart/ApexChartStackedBar.vue"
import { chartJsCustomColors } from "@core/utils/chartJsCustomColors";
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { kFormatter } from "@core/utils/formatters";


const props = defineProps({
  savingData: {
    type: Object,
    required: true,
  },
});

const savingStore = useSavingsStore();

const savingData = structuredClone(toRaw(props.savingData));
const totalBaseline = ref();
const totalProjectSaving = ref();
const totalRealisedSavingBaseLine = ref();
const totalRealisedSaving = ref();
const totalRealisedSavingPer = ref();
const totalProjectedSavingPer = ref();
const projectCostReduction = ref();
const projectCostAvoidance = ref();
const realised_cost_avoidance = ref();
const realised_cost_reduction = ref();
const currencySymbol = ref();

onMounted(() => {
  fetchSavingMilestones();
  savingData.value = structuredClone(toRaw(props.savingData));
  totalBaseline.value = savingData.value.total_base_line_spend;
  realised_cost_avoidance.value = savingData.value.realised_cost_avoidance;
  realised_cost_reduction.value = savingData.value.realised_cost_reduction;
  projectCostReduction.value = savingData.value.project_cost_reduction;
  projectCostAvoidance.value = savingData.value.project_cost_avoidance;
  totalProjectSaving.value = savingData.value.total_project_saving;
  totalRealisedSavingBaseLine.value = savingData.value.total_realised_saving_base_line;
  totalRealisedSaving.value = savingData.value.total_realised_saving;
  totalRealisedSavingPer.value = savingData.value.total_realised_saving_perc;
  totalProjectedSavingPer.value = savingData.value.total_project_saving_perc;
  currencySymbol.value = savingData.value.currencySymbol;

});


watch(props, () => {
  savingData.value = structuredClone(toRaw(props.savingData));
  totalBaseline.value = savingData.value.total_base_line_spend;
  realised_cost_avoidance.value = savingData.value.realised_cost_avoidance;
  realised_cost_reduction.value = savingData.value.realised_cost_reduction;
  projectCostReduction.value = savingData.value.project_cost_reduction;
  projectCostAvoidance.value = savingData.value.project_cost_avoidance;
  totalProjectSaving.value = savingData.value.total_project_saving;
  totalRealisedSavingBaseLine.value = savingData.value.total_realised_saving_base_line;
  totalRealisedSaving.value = savingData.value.total_realised_saving;
  totalRealisedSavingPer.value = savingData.value.total_realised_saving_perc;
  totalProjectedSavingPer.value = savingData.value.total_project_saving_perc;
  currencySymbol.value = savingData.value.currencySymbol;

  fetchSavingMilestones();// fetch the milestones
}, {deep: true});

const isChartLoaded = ref(false);

const savingsAmountGraph = ref({
  labels: ['Projected Savings', 'Realised Savings'],
  datasets: [],
  backgroundColor: [chartJsCustomColors.barChartYellow, chartJsCustomColors.areaChartBlue, chartJsCustomColors.passedContract, chartJsCustomColors.horizontalBarInfo],
  prefix: '',
  suffix: '',
});

const fetchSavingMilestones = () => { 
  isChartLoaded.value = false;

  savingStore.fetchSavingMilestone({
    saving_id: props.savingData.id,
  })
  .then((response) => {
    const savingMilestones = response.data.milestoneData;

    savingsAmountGraph.value['prefix'] = currencySymbol.value || '';

    const costReduction = {
      name: 'Cost Reduction',
      data: [0, 0],
    }

    const costAvoidance = {
      name: 'Cost Avoidance',
      data: [0, 0],
    }

    for(const milestone of savingMilestones){

      costReduction['data'][0] += milestone.project_cost_reduction;
      costAvoidance['data'][0] += milestone.project_cost_avoidance;

      costReduction['data'][1] += milestone.realised_cost_reduction;
      costAvoidance['data'][1] += milestone.realised_cost_avoidance;

    }

    costReduction['data'][0] = costReduction['data'][0]?.toFixed(2);
    costAvoidance['data'][0] = costAvoidance['data'][0]?.toFixed(2);

    costReduction['data'][1] = costReduction['data'][1]?.toFixed(2);
    costAvoidance['data'][1] = costAvoidance['data'][1]?.toFixed(2);

    savingsAmountGraph.value['datasets'] = [costReduction, costAvoidance];

  })
  .catch((error) => {
    console.log(error);
  })
  .finally(() => {
      isChartLoaded.value = true;
  });
};
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard :title="$t('Financial Information')">
       
        <VCardText>
          <VRow>
            <VCol
              cols="12"
            >
              <VRow>
                <VCol
                  cols="12"
                  lg="6"
                >
                  <h6 class="text-h5 mb-5 font-weight-medium text-underline">
                    {{ $t('Projected Savings') }}
                  </h6>

                  <VTable class="billing-address-table">
                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Total Baseline Spend') }} : <span class="text-body-1 mb-0">{{ totalBaseline && currencySymbol + kFormatter(totalBaseline) || "--" }}</span>
                        </h6>
                      </td>
                    </tr>

                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Projected Cost Reduction') }} : <span class="text-body-1 mb-0">{{ projectCostReduction && currencySymbol + kFormatter(projectCostReduction) || "--"}}</span>
                        </h6>
                      </td>
                    </tr>

                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Total Projected Savings') }} : <span class="text-body-1 mb-0">{{ totalProjectSaving && currencySymbol + kFormatter(totalProjectSaving) || "--"}}</span>
                        </h6>
                      </td>
                    </tr>
                  </VTable>

                </VCol>
                <VCol
                  cols="12"
                  lg="6"
                >
                  <h6 class="text-h6 mb-5 text-underline">
                    
                  </h6>

                  <VTable class="billing-address-table mt-10">
                     <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2" style="visibility:hidden">
                          {{ $t('Projected Cost Avoidance') }} : <span class="text-body-1 mb-0"> {{ projectCostAvoidance && currencySymbol + kFormatter(projectCostAvoidance) || "--"}}</span>
                        </h6>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Projected Cost Avoidance') }} : <span class="text-body-1 mb-0"> {{ projectCostAvoidance && currencySymbol + kFormatter(projectCostAvoidance) || "--"}}</span>
                        </h6>
                      </td>
                    </tr>

                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Total Projected Savings') }} (%) : <span class="text-body-1 mb-0">{{ totalProjectedSavingPer && totalProjectedSavingPer + '%' || "--" }}</span>
                        </h6>
                      </td>
                    </tr>
                  </VTable>
                </VCol>
              </VRow>
            </VCol>

            <VCol
              cols="12"
            >
              <VRow>
                <VCol
                  cols="12"
                  lg="6"
                >
                  <h6 class="text-h5 mb-5 font-weight-medium text-underline">
                    {{ $t('Realised Savings') }}
                  </h6>

                  <VTable class="billing-address-table">
                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Total Baseline Spend Against Realised Savings') }} : <span class="text-body-1 mb-0">{{ totalRealisedSavingBaseLine && currencySymbol + kFormatter(totalRealisedSavingBaseLine) || "--" }}</span>
                        </h6>
                      </td>
                    </tr>

                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Realised Cost Reduction') }} :  <span class="text-body-1 mb-0">{{ realised_cost_reduction && currencySymbol + kFormatter(realised_cost_reduction) || "--"}}</span>
                        </h6>
                      </td>
                    </tr>

                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Total Realised Savings') }} : <span class="text-body-1 mb-0">{{ totalRealisedSaving && currencySymbol + kFormatter(totalRealisedSaving) || "--" }}</span>
                        </h6>
                      </td>
                    </tr>
                  </VTable>
                </VCol>
                
                <VCol
                  cols="12"
                  lg="6"
                >

                  <h6 class="text-h6 mb-5 text-underline">
                    
                  </h6>

                  <VTable class="billing-address-table mt-10">
                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2" style="visibility:hidden">
                          {{ $t('Realised Cost Avoidance') }} : <span class="text-body-1 mb-0" >{{ realised_cost_avoidance && currencySymbol + kFormatter(realised_cost_avoidance) || "--" }}</span>
                        </h6>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Realised Cost Avoidance') }} : <span class="text-body-1 mb-0">{{ realised_cost_avoidance && currencySymbol + kFormatter(realised_cost_avoidance) || "--" }}</span>
                        </h6>
                      </td>
                    </tr>

                    <tr>
                      <td>
                        <h6 class="text-h6 text-wrap mb-2">
                          {{ $t('Total Realised Savings') }} (%) : <span class="text-body-1 mb-0">{{ totalRealisedSavingPer && totalRealisedSavingPer + '%' || "--" }}</span>
                        </h6>
                      </td>
                    </tr>
                  </VTable>
                </VCol>
              </VRow>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Savings Amount By Milestone & Savings Type Chart -->
    <VCol cols="12">
      <VCard :title="$t('Savings Amount By Savings Type')">
        <VCardText>
          <ApexChartStackedBar v-if="isChartLoaded" :data="savingsAmountGraph" :height="400" />
        </VCardText>
      </VCard>
    </VCol> 
  </VRow>

</template>

<style lang="scss">
.billing-address-table {
  tr {
    td:first-child {
      inline-size: 148px;
    }
  }
}
</style>
