<script setup>
import {
  avatarText,
  kFormatter,
} from '@core/utils/formatters'
import { useSnackBarStore } from "@core/utils/snackBar";
import { formatDate } from "@/plugins/date/dateUtils";
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { scrollTop } from "@/plugins/dashboards/utils";
import { RelationshipType } from '@core/enums'
import { isAdministrator } from "@core/utils";

const snackBarStore = useSnackBarStore();
const savingStore = useSavingsStore();
const props = defineProps({
  savingData: {
    type: Object,
    required: true,
  },
})
const loggedInUserId = useLocalStorage('loginUserId') // log in user
const emit = defineEmits([
  'editSaving',
])
const savingData = ref("")
const isSavingSummaryEditDialogVisible = ref(false)
const selectedSavingsRecord = ref({});
const activateDialog = ref(false);
const supplierId = ref({});
const approveLoader = ref(false);
const isRejectDialog = ref(false);
const approveWarningDialog = ref(false);
const customFields = ref([]);

watch(props, () => {
  savingData.value = structuredClone(toRaw(props.savingData));
  supplierId.value = savingData.value?.supplier_id || {};
  customFields.value = savingData.value?.additionalData?.custom_fields || [];
})


const statusButtonColor = computed(() => {
  if(props.savingData.archive == 1)
    return 'success';

  return 'error';
});

const statusButtonLabel = computed(() => {
  if(props.savingData.archive == 1)
    return 'Unarchive';

  return 'Archive';
});


const updateResponse = () => {
  emit('editSaving');
}

const openSavingSummaryEditDialog = () => {
  scrollTop()
  .then(() => {
    isSavingSummaryEditDialogVisible.value = true
  });
}


// // --------------------------- Archive Supplier Dialog ------------------------- //

const archiveDialog = ref(false) 
const archiveUnarchiveId = ref(null);
const savingID = ref(null);
const archiveDialogTitle = ref("Archive saving");
const archiveDialogMessage = ref(
   "Are you sure you want to archive this saving?You will not be able to link this saving to savings or savings activities or them to sourcing activities. Archived savings will not be removed from already created records."
);
const confirmArchive = (val) => {
  if(val){
    archiveUnarchive(archiveUnarchiveId.value, savingID.value)
  }
}
const showArchiveUnarchiveDialog = (val) => {
  const isArchive = val.archive == 1;
  const UnArchive = val.archive == 0;
  if (isArchive) {
    archiveDialogTitle.value = "Unarchive saving";
    archiveDialogMessage.value = "Are you sure you want to unarchive this saving?";
  } else if(UnArchive){
    archiveDialogTitle.value = "Archive saving";
    archiveDialogMessage.value = "Are you sure you want to archive this saving ?";
  }else{
    archiveDialogTitle.value = "Active saving";
    archiveDialogMessage.value = "Are you sure you want to active this saving?";
  }

  archiveUnarchiveId.value = val.archive;
  savingID.value = val.id;
  archiveDialog.value = true;
}
const archiveUnarchive = (savingStatus,savingID) => {
  const status = savingStatus == 1 ? 0 : 1;
  const data = {
    'archive_status':status,
    'id':savingID,
  }

  savingStore.updateArchiveStatus(data)
  .then((response) => {
    snackBarStore.message = response.data.message;
    snackBarStore.type = 'success';
    updateResponse();
  })
  .catch((error) => {
    const { message } = error.response.data
    snackBarStore.message = message;
    snackBarStore.type = 'error';
  })
  .finally(() => {
    snackBarStore.snackbar = true;
  });
};

const editSavingResponse = () => {
  updateResponse();
}

const supplierIcon = computed(() =>{
  return savingData.value.supplier_image;
});

const supplierName = computed(() =>{
  return savingData.value.supplier;
});

const savingStatus = computed(() => {
  if (savingData.value.status === 0) {
    return "Not Started";
  } else if (savingData.value.status === 1) {
    return "In Progress";
  } else if (savingData.value.status === 2) {
    return "Completed";
  } else {
    return "Cancelled";
  }
});

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
const removeDuplicatesDep = computed(() => {
  if (savingData.value && savingData.value.department) {
    const departments = savingData.value.department.split(',').map(dep => dep.trim());
    const uniqueDepartments = [...new Set(departments)];
    return uniqueDepartments.join(', ');
  }
  return '';
})

const removeDuplicatesLoc = computed(() => {
  if (savingData.value && savingData.value.location) {
    const locations = savingData.value.location.split(',').map(loc => loc.trim());
    const uniqueLocations = [...new Set(locations)];
    return uniqueLocations.join(', ');
  }
  return '';
})

const showActivateDilaog = (savings) => {
  selectedSavingsRecord.value = savings;
  activateDialog.value = true;
}

const activateSavings = () => {

  const postData = {
    id: selectedSavingsRecord.value.id,
    is_draft: 0,
  };

  savingStore.changeStatus(postData)
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

    updateResponse(); // fetch saving record

  });

}


const isApprover = computed(() => {
  // if logged in user is approver
  return savingData.value.additionalData?.saving_approver?.approver_id == loggedInUserId.value;;
})

const isCreator = computed(() => {
  return savingData.value.additionalData?.created_by == loggedInUserId.value;
});

const savingsNeedApproval = computed(() => {
  // if supplier need_approval == 1 return true else return false;
  return savingData.value?.additionalData?.needs_approval == 1;
})

const savingsRejected = computed(() => {
  // if supplier rejected return true else return false;
  return savingData.value.additionalData?.approval_status == 2;
})

const showRejectDialog = () => {
  isRejectDialog.value = true;
}

const reapproveLoader = ref(false);

const requestReApproval = () => {
  reapproveLoader.value = true;

  const postData = {
    saving_id: savingData.value.id,
    user_id: savingData.value?.additionalData?.created_by,
    saving_name: savingData.value.additionalData?.name,
    reapproval_count: savingData.value?.additionalData?.reapproval_count,
  }; 

  savingStore.requestReApproval(postData)
  .then((response) => {
    snackBarStore.message = response.data.message;
    snackBarStore.type = 'success';
  })
  .catch((error) => {
    const { message } = error.response.data
    snackBarStore.message = message;
    snackBarStore.type = 'error';
  })
  .finally(() => {
    snackBarStore.snackbar = true;
    reapproveLoader.value = false;

    // update the response and emit to the profile.
    updateResponse();
  });
}

const rejectLoader = ref(false);

const rejectResponse = (response) => {

  rejectLoader.value = true;

  const postData = {
    saving_id: savingData.value.id,
    reject_reason: response.reason,
    user_id: savingData.value?.additionalData?.created_by,
    saving_name: savingData.value.additionalData?.name
  }; 

  savingStore.rejectSavingsApproval(postData)
  .then((response) => {
    snackBarStore.message = response.data.message;
    snackBarStore.type = 'success';
  })
  .catch((error) => {
    const { message } = error.response.data
    snackBarStore.message = message;
    snackBarStore.type = 'error';
  })
  .finally(() => {
    snackBarStore.snackbar = true;
    rejectLoader.value = false;

    // update the response and emit to the profile.
    updateResponse();
  });
}

const confirmApprove = () => {
  approveSavings();
}

const approveSavings = () => {
  approveLoader.value = true;

  const postData = {
    saving_id: savingData.value.id,
  }; 

  savingStore.approveSavings(postData)
  .then((response) => {
    snackBarStore.message = response.data.message;
    snackBarStore.type = 'success';
  })
  .catch((error) => {
    const { message } = error.response.data
    snackBarStore.message = message;
    snackBarStore.type = 'error';
  })
  .finally(() => {
    snackBarStore.snackbar = true;
    approveLoader.value = false;

    // update the response and emit to the profile.
    updateResponse();
  });
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
  <VRow>
    <!-- SECTION User Details -->
    <VCol cols="12">
      <VCard >
        <VCardText class="text-center pt-15">
          <!-- 👉 Avatar -->

          <RouterLink
            v-if="savingData?.supplier_image || savingData?.supplier"
            :to="{ name: 'supplier-view-id', params: { id: supplierId }}"
          >
            <VAvatar
              rounded
              :size="100"
              :color="!savingData?.supplier_image ? 'primary' : undefined"
              :variant="!savingData?.supplier_image ? 'tonal' : undefined"
            >
            
              <VImg
                v-if="savingData?.supplier_image"
                :src="savingData?.supplier_image"
              />
              <span
                v-else
                class="text-5xl font-weight-medium"
              >
                {{ avatarText(savingData?.supplier) }}
              </span>
            </VAvatar>
          </RouterLink>

          <VAvatar
            v-else
            rounded
            :size="100"
            color="primary"
            variant="tonal"
          >

            <span
              class="text-5xl font-weight-medium"
            >
              {{ avatarText(savingData?.savingName) }}
            </span>
          </VAvatar>

          <h6 class="text-h4 mt-4">
            {{ savingData?.savingName }}
          </h6>

        </VCardText>

        <VCardText>

          <div class="d-flex justify-center mt-3">
            <div class="d-flex align-center me-8">
              <VAvatar
                :size="38"
                rounded
                color="warning"
                variant="tonal"
                class="me-3"
              >
                <VIcon icon="tabler-coins"  color="warning" />
              </VAvatar>

              <div>
                <h6 class="text-h6">
                  {{ savingData.currencySymbol +''+ kFormatter(savingData.total_project_saving) }}
                </h6>
                <span class="text-sm">{{ $t('Projected Savings') }}</span>
              </div>
            </div>
            <div class="d-flex align-center me-4">
              <VAvatar
                :size="38"
                rounded
                color="success"
                variant="tonal"
                class="me-3"
              >
                <VIcon icon="tabler-rocket" color="success"/>
              </VAvatar>
              <div>
                <h6 class="text-h6">
                  {{ savingData.currencySymbol +''+ kFormatter(savingData.total_realised_saving) }}
                </h6>
                <span class="text-sm">{{ $t('Realised Savings') }}</span>
              </div>
            </div>
          </div>
        </VCardText>

        <VDivider />

        <!-- 👉 Details -->
        <VCardText>
          <p class="text-sm text-uppercase text-disabled">
            {{ $t('DETAILS') }}
          </p>

          <!-- 👉 saving Details list -->
          <VList class="card-list mt-2">

            <!-- <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{ $t('Contract Title') }}:
                  <span class="text-body-1 text-wrap">
                    {{ savingData.saving_title || "--"}}
                  </span>
                </h6>
              </VListItemTitle>
            </VListItem> -->

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{ $t('Status') }}:
                  <VChip :color="evaluateStatus(savingData).color">
                    {{ $t(evaluateStatus(savingData).title)}}
                  </VChip>
                </h6>
   
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Saving Type') }}:
                  <span class="text-body-1">{{ savingData.saving_type || "--" }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                   {{ $t('Currency') }}:
                  <span class="text-body-1">{{ savingData?.currency || "--" }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                   {{ $t('Category') }}:
                  <span class="text-body-1">{{ savingData?.category || "--" }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                   {{ $t('Sub Category') }}:
                  <span class="text-body-1">{{ savingData?.subcategory || "--" }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Location') }}:
                  <span class="text-body-1">{{ removeDuplicatesLoc || "--"}}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Department') }}:
                  <span class="text-body-1">{{ removeDuplicatesDep || "--"  }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Supplier Name') }}:

                  <RouterLink
                    v-if="savingData?.supplier"
                    :to="{ name: 'supplier-view-id' ,params: { id: savingData?.supplier_id }}"
                    class="font-weight-medium user-list-name"
                  >
                    {{ savingData?.supplier }} 
                  </RouterLink>

                  <span v-else class="text-body-1">{{ "--" }}</span>

                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Associated Contract') }}:

                  <RouterLink
                    v-if="savingData?.contract"
                    :to="{ name: 'contract-view-id' ,params: { id: savingData?.contractId }}"
                    class="font-weight-medium user-list-name"
                  >
                    {{ savingData?.contract }} 
                  </RouterLink>

                  <span v-else class="text-body-1">{{ "--" }}</span>

                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Total Baseline Spend') }}:
                  <span class="text-body-1">{{ savingData?.total_base_line_spend && savingData?.currencySymbol + kFormatter(savingData?.total_base_line_spend) || "--"  }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Total Projected Savings') }}:
                  <span class="text-body-1">{{ savingData?.total_project_saving && savingData?.currencySymbol + kFormatter(savingData?.total_project_saving) || "--"  }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Total Projected Savings') }} (%):
                  <span class="text-body-1">{{ savingData?.total_realised_saving_perc && savingData?.total_project_saving_perc + '%' || '--' }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Total Baseline Spend Against Realised Savings') }}:
                  <span class="text-body-1">{{ savingData?.total_realised_saving_base_line && savingData?.currencySymbol + kFormatter(savingData?.total_realised_saving_base_line) || '--' }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
              
                <h6 class="text-h6 text-wrap">
                  {{ $t('Total Realised Savings') }}:
                  <span class="text-body-1">{{ savingData?.total_realised_saving && savingData?.currencySymbol + kFormatter(savingData?.total_realised_saving) || '--'}}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Total Realised Savings') }} (%):
                  <span class="text-body-1">{{ savingData?.total_realised_saving_perc && savingData?.total_realised_saving_perc + '%' || "--"  }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Savings Due Date') }}:
                  <span class="text-body-1">{{ formatDate(savingData?.end_date) }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Saving Parent/Child Relationship') }}:
                  <span class="text-body-1">{{ getRelationType(savingData?.additionalData?.parent_child?.relation_type)  }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Parent/Child of Saving') }}:

                  <RouterLink
                    v-if="savingData?.additionalData?.parent_child?.saving_record?.name"
                    :to="{ name: 'savings-view-id' ,params: { id: savingData?.additionalData?.parent_child?.saving_record?.id }}"
                    class="font-weight-medium user-list-name"
                  >
                    {{ savingData?.additionalData?.parent_child?.saving_record?.name }} 
                  </RouterLink>

                  <span v-else class="text-body-1">{{ "--" }}</span>
                  
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Created On') }}:
                  <span class="text-body-1">{{ formatDate(savingData?.created_at )  }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>
            
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t('Created By') }}:
                  <span class="text-body-1">{{ savingData?.name || "--"  }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{ $t('Description/Notes') }}:
                  <span class="text-body-1 text-wrap">
                    {{ savingData?.description || "--"}}
                  </span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <!-- Show first 20 custom fields -->
            <VListItem v-for="(field, index) in customFields.slice(0, 20)" :key="index">
              <VListItemTitle>
                <h6 class="text-h6 text-wrap">
                  {{ $t(field?.field_name) }}:
                  <span class="text-body-1" v-if="field?.field_type == 3">{{ formatDate(field?.formatted_value) }}</span>
                  <span class="text-body-1" v-else>{{ field?.formatted_value || "--" }}</span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <!-- Show remaining fields in expandable group -->
            <VListGroup v-if="customFields.length > 20">
              <template #activator="{ props }">
                <VListItem v-bind="props" title="More..." />
              </template>

              <VListItem v-for="(field, index) in customFields.slice(20)" :key="`group-${index}`">
                <VListItemTitle>
                  <h6 class="text-h6 text-wrap">
                    {{ $t(field?.field_name) }}:
                    <span class="text-body-1" v-if="field?.field_type == 3">{{ formatDate(field?.formatted_value) }}</span>
                    <span class="text-body-1" v-else>{{ field?.formatted_value || "--" }}</span>
                  </h6>
                </VListItemTitle>
              </VListItem>
              
            </VListGroup>

          </VList>
        </VCardText>

        <!-- 👉 Edit and Suspend button -->
       <VCardText class="d-flex justify-center">
          <VBtn
            v-if="$can('edit', 'savings')"
            variant="elevated"
            class="me-4"
            @click="openSavingSummaryEditDialog"

          > 
            {{ $t('Edit') }}
          </VBtn>

          <VBtn
            v-if="$can('archive', 'savings') && (savingData.additionalData?.approval_status == 1)"
            variant="tonal"
            :color="statusButtonColor"
            class="me-4"
            @click="showArchiveUnarchiveDialog(savingData)"
          >
            {{ $t(statusButtonLabel) }}
          </VBtn>

          <VBtn
            v-if="savingData.is_draft && $can('edit', 'savings')"
            variant="elevated"
            color="warning"
            class="me-4"
            @click="showActivateDilaog(savingData)"
          > 
            {{ $t('Publish') }}
          </VBtn>

        </VCardText>

        <VCardText class="d-flex justify-center" v-if="savingsNeedApproval && (isApprover || isAdministrator())">
          <VBtn
            v-if="!approveLoader"
            variant="tonal"
            class="me-4"
            color="success"
            @click="approveWarningDialog = true"
          >
            {{ $t('Approve Saving') }}
          </VBtn>

          <VBtn
            v-if="approveLoader || rejectLoader"
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

          <VBtn
            v-if="!rejectLoader"
            variant="tonal"
            class="me-4"
            color="error"
            @click="showRejectDialog"
          >
            {{ $t('Reject Saving') }}
          </VBtn>
        </VCardText>

        <VCardText class="d-flex justify-center" v-if="savingsRejected && isCreator">
          <VBtn
            v-if="!reapproveLoader"
            variant="tonal"
            class="me-4"
            color="success"
            @click="requestReApproval"
          >
            {{ $t('Send for approval') }}
          </VBtn>

          <VBtn
            v-if="reapproveLoader"
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
        </VCardText>
      </VCard>
    </VCol>
    <!-- !SECTION -->
  </VRow>

  <SimpleConfirmDialog
    v-model:isDialogVisible="archiveDialog"
    @click-ok="confirmArchive"
    :title="archiveDialogTitle"
    :sub-title="archiveDialogMessage"
    ok-btn-text="Yes"
  />
  
  <!-- 👉 Activate savings dialog -->
  <SimpleConfirmDialog
    v-model:isDialogVisible="activateDialog"
    @click-ok="activateSavings"
    title="Publish Savings Record?"
    sub-title="Are you sure you want to publish this savings record?"
    ok-btn-text="Yes"
  />

  <!-- 👉 Edit user info dialog -->
  <SavingInfoEditDialog
    v-model:isDialogVisible="isSavingSummaryEditDialogVisible"
    :saving-data="props.savingData"
    :custom-fields="customFields"
    @response="editSavingResponse"
  />

  <RejectionDialog 
    v-model:isDialogVisible="isRejectDialog"
    @response="rejectResponse"
  />

  <SimpleConfirmDialog
    v-model:isDialogVisible="approveWarningDialog"
    @click-ok="confirmApprove"
    title="Are you sure you want to approve this saving?"
    sub-title="Are you sure you want to approve this saving? This saving will be marked as active after it has been approved"
    ok-btn-text="Yes"
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
