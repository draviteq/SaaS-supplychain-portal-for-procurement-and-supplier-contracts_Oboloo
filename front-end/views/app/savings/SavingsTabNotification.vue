<script setup>
import { VDataTableServer } from "vuetify/labs/VDataTable";
import { ContractCommentsHeaders } from "@core/utils/tableHeaders";
import { useContractStore } from "@/router/stores/useContractStore";
import { useSnackBarStore } from "@core/utils/snackBar";
import { paginationMeta } from "@/plugins/dashboards/utils";
import { avatarText } from '@core/utils/formatters'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import { formatDate } from "@/plugins/date/dateUtils"
import { useSavingsStore } from "@/router/stores/useSavingsStore";
import { getFormattedCustomFieldValue } from "@/plugins/common";
import { useI18n } from "vue-i18n";

const I18n = useI18n();
const savingStore = useSavingsStore();
const snackBarStore = useSnackBarStore();
const savingCardNotification = ref([]);
const savingsComments = ref([]);
const currentPage = ref(1);
const itemsPerPage = ref(5);
const searchQuery = ref(null);
const options = ref({
  itemsPerPage: 10,
  page: 1,
});
const totalPage = ref(0);
const totalItems = ref(0);
const totalComment = ref(0)
const lastItemsPerPage = ref(options.value.itemsPerPage);
const debounceTimeout = ref(null);
const lastCurrentPage = ref(currentPage.value);
const deleteResponse = ref(null);
const authUser = localStorage.getItem('loginUserId');
const customFields = ref([]); // store custom field as header
const isCustomFieldLoaded = ref(true);

onMounted(() => {
  fetchNotification(Number(route.params.id));
})
const route = useRoute()


const fetchNotification = (id) => {
  const params = {
    id: id,
  }

  savingStore.savingNotification(params) 
  .then(response => {
    const { notifications } = response.data;
    savingCardNotification.value = notifications;
   
  })
}

watchDebounced(() => ({
  currentPage: options.value.page,
  itemsPerPage: options.value.itemsPerPage,
  searchQuery: searchQuery.value,

}),
  (v) => {
    fetchData() 
  },
  { debounce: 500},
)

const fetchData = () => { 
  isCustomFieldLoaded.value = false;

  savingStore.fetchComment({
    saving_id:Number(route.params.id),
    options: options.value,
    search: searchQuery.value,
  })
  .then((response) => {
    savingsComments.value = response.data.data;
    totalComment.value = response.data.totalItems;
    options.value.page = +response.data.page;
    totalPage.value = response.data.totalPage;
    customFields.value = response.data.custom_fields || [];
  })
  .catch((error) => {
    console.log(error);
  })
  .finally(() => {
    isCustomFieldLoaded.value = true;
  });
};
const truncateText = (text, limit) => {
  if (text.length > limit) {
    return text.substring(0, limit) + '...';
  }
  return text;
};

const cid = ref();
const isAddSavingCommentDialogVisible = ref(false)
const isViewSavingCommentDialogVisible = ref(false)
const commentData = () => {
  fetchData();
};

const flattenedSavingComment = computed(() => {
  return savingsComments.value.map((item) => {
    return {
      id: item.id || "N/A",
      name: item.users.name || "N/A",
      comment: item.comment || "N/A",
      created_at: item.created || "N/A",
      actions: item.id || "N/A",
      userid: item.user_id,
      user_image: item.users.user_image,
      custom_fields: item.custom_fields,
    };
  });
});

const commentTableModifiedHeaders = computed(() => {
  return [
    {
      title: I18n.t('Name'),
      key: 'name',
      visible: true,
      fixed: true,
    },
    {
      title: I18n.t('Note'),
      key: 'comment',
      visible: true,
    },
    {
      title: I18n.t('Created Date'),
      key: 'created_at',
      visible: true,
    },
    ...customFields.value?.map(item => 
      ({
        title: I18n.t(item.field_name),
        key: customHeaderKey(item),
        sortable: false,
        visible: false,
        fixed: false
      })
    ),
    {
      title: I18n.t('Action'),
      key: "actions",
      sortable: false,
      visible: true,
    },
  ]
})

const commentTableHeaders = ref(structuredClone(toRaw(commentTableModifiedHeaders.value.filter(item => item.visible))));

const customHeaderKey = (header) => {
  return header?.field_name?.split(' ')?.join('_')+'_'+header?.id;
}

const setId = id => {
  
  if(id != null){
    cid.value = id;
    isViewSavingCommentDialogVisible.value = true
  }
  
}

const deleteDialog = ref(false)
const selectedComment = ref('');
const deleteComment = (card) => {
    selectedComment.value = card;
  deleteDialog.value = true;
}
const confirmDeleteSupplierContact = (val) => {
  
  if(val){
    commentDeleteApi(selectedComment.value)
  }
}
const commentDeleteApi = (itemId) => { 
   
  savingStore.deleteOneComment({
     id: itemId,
   })
  .then((response) => {
   deleteResponse.value = response;
   snackBarStore.message = response.data.message;
   snackBarStore.type = "success";
   fetchData();
 })
 .catch((error) => {
  console.error("Error deleting item:", error);
  snackBarStore.message = "Error deleting item";
  snackBarStore.type = "error";
 })
 .finally(() => {
   snackBarStore.snackbar = true;
 });
};

const selectedHeaders = (headers) => {
  commentTableHeaders.value = headers;
  const temp = savingsComments.value;
  savingsComments.value = [];
  savingsComments.value = temp;
};

</script>

<template>
    <VRow>
        <VCol cols="12">
            <!-- 👉 Activity timeline -->
            <VCard 
              class="d-flex flex-column"
              :title="$t('Saving Notifications')" 
              :height="'auto'"
            > 
            <VDivider />
      
           <VCardText>
            <span class="d-flex justify-center flex-wrap mt-3" v-if="savingCardNotification.length == 0">No Notification Found</span>
      
             <PerfectScrollbar
                :options="{ wheelPropagation: false }"
                 style="max-block-size: 13.75rem;"
                >
                <VTimeline
                  density="compact"
                  align="start"
                  truncate-line="both"
                  class="v-timeline-density-compact px-5"
                >
                 <VTimelineItem
                    v-for="notificationCard in savingCardNotification"
                    :key="notificationCard"
                    dot-color="warning"
                    size="x-small"
                  >
                    <div class="d-flex justify-space-between align-center flex-wrap gap-2 mb-3">
                      <span class="app-timeline-title">
                        {{ $t(notificationCard.message) }}
                      </span>
                      <span class="app-timeline-meta">{{formatDate(notificationCard.formatted_created_at)}}</span>
                    </div>
      
                    <p class="app-timeline-text mb-1">
                      {{ notificationCard?.sub_message_translation ? $t(notificationCard?.sub_message_translation, JSON.parse(notificationCard.data)) : $t(notificationCard?.sub_message) }}
                    </p>
                    
                  </VTimelineItem>
                </VTimeline>
              </PerfectScrollbar>
              </VCardText>
            </VCard>
          </VCol>
      </VRow>
      <VRow>
    <VCol cols="12">
      <VCard :title="$t('Notes')">
        <VCardText class="d-flex flex-wrap py-4 gap-4">
          <div class="d-flex gap-3">
            <AppSelect
              :model-value="options.itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 20, title: '20' },
                { value: 30, title: '30' },
              ]"
              style="width: 6.25rem;"
              @update:model-value="options.itemsPerPage = parseInt($event, 10), options.page = 1"
            />
            <ColumnChooser
              v-if="isCustomFieldLoaded"
              :headers="commentTableModifiedHeaders"
              @selected-headers="selectedHeaders"
            />
          </div>

          <VSpacer />

          <div class="d-flex gap-3">  
            <div style="inline-size: 15rem">
              <AppTextField
                v-model="searchQuery"
                :placeholder="$t('Search')"
                class="pr-2"
              />
            </div>          
  
            <VBtn v-if="$can('edit', 'savings')"  @click="isAddSavingCommentDialogVisible = true">{{ $t('Add New Note') }}</VBtn>
          </div>

        </VCardText>
        <VDivider />
        <!-- 👉 User Project List Table -->

        <!-- SECTION Datatable -->
        <VDataTableServer
          v-model:items-per-page="options.itemsPerPage"
          v-model:page="options.page"
          :headers="commentTableHeaders"
          :items="flattenedSavingComment"
          :items-length="totalComment"
          class="text-no-wrap"
          @update:options="options = $event"
        >

            <!-- no data text -->
            <template #no-data>
              {{ $t('No data available') }}
            </template>

            <template #item.name="{ item }">
            
              <div class="d-flex align-center">
              <VAvatar
                :size="35"
                :color="!item.raw?.user_image?.file_path ? 'primary' : undefined"
                class="me-3"
              >
                <VImg
                  v-if="item.raw?.user_image?.file_path"
                  :src="item.raw?.user_image?.file_path"
                  cover
                />
                <span v-if="!item.raw?.user_image?.file_path">{{ item.raw?.name ? avatarText(item.raw?.name) : '--' }}
                </span>
              
                <VTooltip
                  v-if="item.raw?.name"
                  location="end"
                  activator="parent"
                >
                 {{item.raw?.name}}
                </VTooltip>
              
             </VAvatar>
              </div>
            </template>

          <template #item.comment="{ item }">
            <div class="d-flex">
              <div>
                <p class="font-weight-medium mb-0">
                  {{ truncateText(item.raw.comment, 50) }} <!-- Limit to 50 characters -->
                </p>
              </div>
            </div>
          </template>

          <template #item.created_at="{ item }">
            <div class="d-flex">
              <div>
                <p class="font-weight-medium mb-0">
                  {{ formatDate(item.raw.created_at) }}
                </p>
              </div>
            </div>
          </template>

          <template v-for="(header) in customFields" v-slot:[`item.${customHeaderKey(header)}`]="{ item }">
            <div class="d-flex align-center" :key="`${customHeaderKey(header)}`">
              {{ getFormattedCustomFieldValue(item.raw.custom_fields, header) }}
            </div>
          </template>

            <!-- Actions -->
            <template #item.actions="{ item }">

              <div class="d-flex align-center">
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  color="medium-emphasis"
                  @click.stop=""
                >
                  <VIcon
                    size="24"
                    icon="tabler-dots-vertical"
                  />

                  <VMenu activator="parent">
                    <VList>
                      <VListItem  @click="setId(item.raw.id)" v-if="$can('edit', 'savings')">
                       
                        <template #prepend>
                          <VIcon icon="tabler-eye" />
                        </template>
                        <VListItemTitle>{{ $t('View') }}</VListItemTitle>
                      </VListItem>

                        <VListItem v-if="$can('archive', 'savings') && (item.raw.userid == authUser)"
                            @click="deleteComment(item.raw.id);"
                        >
                        <template #prepend>
                          <VIcon icon="tabler-trash" />
                        </template>
                        <VListItemTitle>{{ $t('Delete') }}</VListItemTitle>
                      </VListItem>
                    </VList>
                  </VMenu>
                </VBtn>
                </div>
                </template>                                                       

          <!-- Pagination -->
          <template #bottom>
            <VDivider />
            <div
              class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3"
            >
               <p class="text-sm text-disabled mb-0">
                 {{ paginationMeta(options, totalComment) }}
              </p>              
            <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalComment / options.itemsPerPage)"
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
        <!-- !SECTION -->
      </VCard>
    </VCol>
  </VRow>
  
  <ViewSavingCommentDialog 
      v-model:is-view-dialog-visible="isViewSavingCommentDialogVisible" 
      @comment-data="commentData" :id="cid" 
  />
  
  <AddSavingCommentDialog 
    v-model:is-dialog-visible="isAddSavingCommentDialogVisible"
    @comment-data="commentData" 
    :custom-fields="customFields"
    :savingId="Number(route.params.id)"
  />

   <SimpleConfirmDialog
    v-model:isDialogVisible="deleteDialog"
    @click-ok="confirmDeleteSupplierContact"
    title="Delete Comment"
    sub-title="Are you sure you want to delete this comment ?"
    ok-btn-text="Yes"
  />
</template>

<style lang="scss">

</style>
