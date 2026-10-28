<script setup>

import SavingDetails from '@/views/apps/savings/SavingDetails.vue'
import BuilderOptions from '@/views/apps/savings/BuilderOptions.vue'
import MilestoneBuilder from '@/views/apps/savings/MilestoneBuilder.vue'
import Overview from '@/views/apps/savings/Overview.vue'
import { useSavingsStore } from '@/router/stores/useSavingsStore'
import { useSnackBarStore } from "@core/utils/snackBar";

const savingsStore = useSavingsStore()
const snackBarStore = useSnackBarStore();
const currentStep = ref(0);
const populatedMilestone = ref([]);
const isAutomaticMilestoneBuilder = ref(false);
const route = useRoute()
const router = useRouter()
const draftMilestones = ref([]);

const draftSavings = ref({});

onMounted(() => {
  if(route.query.id)
    fetchSavings(route.query.id)
})

watch(route, () => {
  if(route.name != 'savings-create'){
    savingsStore.recentSavingsId = null;
  }
}, 
{ deep: true }
);

const fetchSavings = (id) => {
  const params = {
    id: id,
  }

  savingsStore.fetchOneSavings(params) 
  .then(response => {
    const { savingsData } = response.data;
    draftSavings.value = savingsData;
    if(!savingsData){
      router.replace({
        name: 'savings-list',
      })
    }
    if(savingsData.is_draft == 0){
      router.replace({
        name: 'savings-view-id',
        params: { 
          id: savingsData.id 
        }
      })
    }
    savingsStore.recentSavingsId = savingsData.id;
    savingsStore.currencyIcon = savingsData?.currency?.currency_symbol || '';
    draftMilestones.value = savingsData.milestones;
    setMilestone(savingsData.milestones, savingsData.milestone_type)

    currentStep.value = savingsData.milestones.length > 0 ? 2 : 1;
  })
}
const previous = (val) => {
  currentStep.value -= val;
}

const next = (val) => {
  currentStep.value += val;
}

const setMilestone = (milestone, type) => { // builder type 0 for auto and 1 for custom
  const milestoneLength = draftMilestones.value.length;
  savingsStore.milestoneType = type;
  if(type == 0) {
    populatedMilestone.value = milestone;
    isAutomaticMilestoneBuilder.value = true;

    // drafted milestone data
    for(let i = 0; i < milestoneLength; i++)
    {
      if(populatedMilestone.value[i]){
        draftMilestones.value[i]['number'] = i+1;
        populatedMilestone.value[i]['name'] = draftMilestones.value[i]['name'];
        populatedMilestone.value[i]['id'] = draftMilestones.value[i]['id'];
      }
    }

  }
  else{
    populatedMilestone.value = milestone;
    isAutomaticMilestoneBuilder.value = false;

    // drafted milestone data
    for(let i = 0; i < milestoneLength; i++)
    {
      if(populatedMilestone.value[i]){
        draftMilestones.value[i]['number'] = i+1;
        populatedMilestone.value[i] = draftMilestones.value[i];
      }
    }
  }
}


const propertyListingSteps = [
  {
    title: 'Savings Summary',
    subtitle: 'Savings Details',
    icon: 'tabler-users',
  },
  {
    title: 'Milestone Builder',
    subtitle: 'Milestone Builder Type',
    icon: 'tabler-list-details',
  },
  {
    title: 'Milestone Creation',
    subtitle: 'Amend Milestones',
    icon: 'tabler-bookmarks',
  },
  {
    title: 'Overview',
    subtitle: 'Confirm Saving',
    icon: 'tabler-coins',
  },
]

const populateSavedMilestone = (milestone) => {

  milestone.forEach((item, index) => {
    item['number'] = index + 1;
  });

  draftMilestones.value = milestone;
  populatedMilestone.value = milestone;
}

const backToMainMenu = () => {
  savingsStore.recentSavingsId = null;
  router.back();
}
</script>

<template>
  <VCard>
    <VRow no-gutters>
      <VCol
        cols="12"
        md="3"
        :class="$vuetify.display.smAndDown ? 'border-b' : 'border-e'"
      >
        <VCardText>
          <!-- v-model:current-step="currentStep" -->
          <AppStepper
            v-model:current-step="currentStep"
            :items="propertyListingSteps"
            direction="vertical"
            icon-size="24"
            class="stepper-icon-step-bg"
            :isActiveStepValid="true"
          />
        </VCardText>
      </VCol>

      <VCol
        cols="12"
        md="9"
      >
        <span class="d-flex flex-1 justify-end savings-step-close-btn">
          <!-- 👉 dialog close btn -->
          <DialogCloseBtn @click="backToMainMenu"/>  
        </span>

        <VCardText style="padding: 0;">
          <VWindow
            v-model="currentStep"
            class="disable-tab-transition"
            :touch="false"
          >
            <VWindowItem>
              <SavingDetails @prev="previous" @next="next" :draft-saving-data="draftSavings"/>
            </VWindowItem>

            <VWindowItem>
              <BuilderOptions @prev="previous" @next="next" @populate-milestone="setMilestone" :draft-saving-data="draftSavings"/>
            </VWindowItem>

            <VWindowItem>
              <MilestoneBuilder :milestone-data="populatedMilestone" @prev="previous" @next="next" :type="isAutomaticMilestoneBuilder" @savedMilestone="populateSavedMilestone"/>
            </VWindowItem>

            <VWindowItem>
              <Overview @prev="previous" @next="next" :type="isAutomaticMilestoneBuilder" :milestone-data="populatedMilestone"/>
            </VWindowItem>
          </VWindow>
        </VCardText>
      </VCol>
    </VRow>
  </VCard>
</template>

<style lang="scss">

.savings-step-close-btn button{
  border-radius: 10px;
}

</style>
<route lang="yaml">
  meta:
    action: create
    subject: savings
</route>
