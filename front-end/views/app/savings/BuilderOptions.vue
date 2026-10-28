<script setup>
import { requiredValidator, integerValidator, floatIntValidator,decimalValidator } from "@validators";
import { useSavingsStore } from '@/router/stores/useSavingsStore'
import { useSnackBarStore } from "@core/utils/snackBar";
import { watch } from "vue";
import { useI18n } from "vue-i18n";

const I18n = useI18n();
const emit = defineEmits([
  'prev',
  'next',
  'populateMilestone',
])

const props = defineProps({
  draftSavingData: {
    type: Object,
    required: false,
  },
})

onMounted(() => {
  
  radioValue.value = props.draftSavingData.milestone_type == 1 ? 'custom' : 'auto' ;
  savingStartDate.value = props.draftSavingData.start_date || null;
  selectedTimePeriod.value = props.draftSavingData.time_period || null;
  selectedFrequency.value = props.draftSavingData.milestone_frequency || null;
  baselineSpend.value = props.draftSavingData.total_base_line_spend || null;
  costReduction.value = props.draftSavingData.project_cost_reduction || null;
  constAvoidance.value = props.draftSavingData.project_cost_avoidance || null;
})

const savingsStore = useSavingsStore()
const snackBarStore = useSnackBarStore();

const refFormAuto = ref()
const refFormCustom = ref()

const isFormValidAuto = ref(false)
const isFormValidCustom = ref(false)

const savingStartDate = ref(null);
const selectedTimePeriod = ref(null);
const selectedFrequency = ref(null);
const baselineSpend = ref(null);
const costReduction = ref(null);
const constAvoidance = ref(null);
const totalSavings = computed(() => {
  return (+constAvoidance.value + +costReduction.value) || 0;
})

const frequencyOfMilestone = ref([
  {value:1, title: 'Monthly'},
  {value:3, title: 'Quarterly'},
  {value:6, title: 'Bi-Annually'},
  {value:12, title: 'Annually'},
]);

const radioValue = ref(props.draftSavingData.milestone_type == 1 ? 'custom' : 'auto');

const radioContent = computed(() => {
  return [
    {
      title: I18n.t('Automated Milestone Builder'),
      desc: I18n.t('Effortless System-Generated Milestones Based on Fixed Time Periods'),
      icon: {
        icon: 'tabler-settings-automation',
        size: '40',
      },
      value: 'auto',
    },
    {
      title: I18n.t('Custom Milestone Builder'),
      desc: I18n.t('Manually Create Your Own Milestones Based On Flexible Time Periods'),
      icon: {
        icon: 'tabler-table',
        size: '40',
      },
      value: 'custom',
    },
  ];
});

const submitAutoBuilder = () => {
  refFormAuto.value?.validate().then(({ valid: isValid }) => {
    if (isValid) {

      savingsStore.timePeriod = selectedTimePeriod.value;
      savingsStore.frequency = selectedFrequency.value;
      savingsStore.totalBaselineSpend = baselineSpend.value;
      savingsStore.costReduction = costReduction.value;
      savingsStore.constAvoidance = constAvoidance.value;
      savingsStore.totalSavings = totalSavings;

      autoBuilderSubmit();
   
    }
  });
}

const submitCustomeBuilder = () => {
  refFormCustom.value?.validate().then(({ valid: isValid }) => {
    if (isValid) {

      savingsStore.timePeriod = selectedTimePeriod.value;
      savingsStore.frequency = selectedFrequency.value;
      savingsStore.totalBaselineSpend = baselineSpend.value;
      savingsStore.costReduction = costReduction.value;
      savingsStore.constAvoidance = constAvoidance.value;
      savingsStore.totalSavings = totalSavings;

      customBuilderSubmit();
    }
  });
};

const autoBuilderSubmit = () => {

  savingsStore.generateAutoMilestone({
    start_date: savingStartDate.value,
    periods_number: selectedTimePeriod.value,
    milestone_frequency: selectedFrequency.value,
    total_baseline: baselineSpend.value,
    cost_reduction: costReduction.value,
    cost_avoidance: constAvoidance.value,
  }).then((response) => {

    emit('populateMilestone', response.data, 0);
    
    submitSecondStep({
      id: savingsStore.recentSavingsId,
      start_date: savingStartDate.value,
      time_period: selectedTimePeriod.value,
      milestone_frequency: selectedFrequency.value,
      total_base_line_spend: baselineSpend.value,
      project_cost_reduction: costReduction.value,
      project_cost_avoidance: constAvoidance.value,
      milestone_type: 0,
    }).then(() => {
      emit('next', 1);
    });    

  }).catch((error) => {
    const { message, errors: errorData} = error.response.data
    snackBarStore.error = errorData;
    snackBarStore.message = message;
    snackBarStore.type = 'error';

    snackBarStore.snackbar = true;
  }).finally(() => {
    // tableLoader.value = false;
  });

}

const customBuilderSubmit = () => {
  let startPoint = 1;
  let endPoint = selectedTimePeriod.value;

  const milestoneData = [];

  while( startPoint <= endPoint ){
    milestoneData.push({
      number: startPoint,
      name: '',
      start_date: null,
      end_date: null,
      base_line_spend: 0,
      project_cost_reduction: 0,
      project_cost_avoidance: 0,
    })
    startPoint++;
  }

  emit('populateMilestone', milestoneData, 1);

  submitSecondStep({
    id: savingsStore.recentSavingsId,
    time_period: selectedTimePeriod.value,
    milestone_type: 1,
  }).then(() => {
    emit('next', 1);
  });
}

const timePeriod = computed(() => {
  let startPoint = 1;
  let endPoint = 60;
  const timePeriods = [];

  while( startPoint <= endPoint ){
    timePeriods.push({value: startPoint, title: startPoint})
    startPoint++;
  }

  return timePeriods;
})


const submitSecondStep = (postData) => {
  return new Promise((resolve, reject) => {
    savingsStore.updateSavingsRecord(postData)
    .then((response) => {
      snackBarStore.message = response.data.message;
      snackBarStore.type = 'success';
      resolve(response);
    })
    .catch((error) => {
      const { message, errors: errorData} = error.response.data
      snackBarStore.error = errorData;
      snackBarStore.message = message;
      snackBarStore.type = 'error';
      reject(error);
    })
    .finally(() => {
      snackBarStore.snackbar = true;
    });
  });
  
}

</script>

<template>
  <VCardText class="pt-2">
    <VCol cols="12">
      <!-- 👉 milestone builder Type  -->
      <CustomRadiosWithIcon
        v-model:selected-radio="radioValue"
        :radio-content="radioContent"
        :grid-column="{ cols: '12', sm: '6' }"
      />
    </VCol>

    <div v-if="radioValue == 'auto'">
      <VForm 
        class="mt-6" 
        ref="refFormAuto"
        v-model="isFormValidAuto"
        @submit.prevent="submitAutoBuilder"
      >
      
        <VRow>
          <VCol cols="12" md="12" >
            <AppDateTimePicker  
              v-model="savingStartDate"
              :placeholder="$t('Start Date')"
              :label="$t('Savings Start Date')"
              class="app-date-time-picker required"
              :required="true"
              :rules="[requiredValidator]"
            />
          </VCol>

          <VCol cols="12" md="12" >
            <AppSelect
              :items="frequencyOfMilestone"
              v-model="selectedFrequency"
              clearable
              clear-icon="mdi-close"
              class="small required"
              :rules="[requiredValidator]"
              label="Frequency of Milestone"
            />
          </VCol>

          <VCol cols="12" md="12">
            <AppSelect
              :items="timePeriod"
              v-model="selectedTimePeriod"
              clearable
              clear-icon="mdi-close"
              class="small required"
              :rules="[requiredValidator]"
              label="Number of Milestones"
            />
          </VCol>

          <VCol cols="12" md="12" >
            <AppTextField 
              v-model="baselineSpend" 
              :rules="[requiredValidator, decimalValidator]"
              :prefix="savingsStore.currencyIcon"
              type="number"
              label="Total Baseline Spend"
              class="required"
            />
          </VCol>

          <VCol cols="12" md="12" >
            <AppTextField 
              v-model="costReduction" 
              :rules="[requiredValidator, decimalValidator]"
              :prefix="savingsStore.currencyIcon"
              type="number"
              label="Projected Cost Reduction (across all milestones)"
              class="required"
            />
          </VCol>

          <VCol cols="12" md="12" >
            <AppTextField 
              v-model="constAvoidance" 
              :rules="[requiredValidator, decimalValidator]"
              :prefix="savingsStore.currencyIcon"
              type="number"
              label="Projected Cost Avoidance (across all milestones)"
              class="required"
            />
          </VCol>

          <VCol cols="12" md="12" >
            <AppTextField 
              v-model="totalSavings" 
              :prefix="savingsStore.currencyIcon"
              type="number"
              class="required"
              readonly
              disabled
              label="Total Projected Savings"
            />
          </VCol>

          <VCol cols="12" class="d-flex gap-4">
            <VBtn
              @click="$emit('prev', 1)"
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
              type="submit"
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

      </VForm>
    </div>

    <div v-if="radioValue == 'custom'">
      <VForm 
        class="mt-6" 
        ref="refFormCustom"
        v-model="isFormValidCustom"
        @submit.prevent="submitCustomeBuilder"
      >
      
        <VRow>
        
          <VCol cols="12" md="12">
            <AppSelect
              :items="timePeriod"
              v-model="selectedTimePeriod"
              clearable
              clear-icon="mdi-close"
              class="small required"
              :rules="[requiredValidator]"
              label="Number of Milestones"
            />
          </VCol>

          <VCol cols="12" class="d-flex gap-4">
            <VBtn
              @click="$emit('prev', 1)"
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
              type="submit"
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

      </VForm>
    </div>
  </VCardText>
</template>

<style lang="scss" scoped>


</style>
