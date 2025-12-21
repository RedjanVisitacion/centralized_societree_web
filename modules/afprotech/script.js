    document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('eventModal');
    if (!modalEl || !window.bootstrap) return;
    const modal = new bootstrap.Modal(modalEl);

    // Handle event card clicks
    document.querySelectorAll('.event-card').forEach(card => {
        card.addEventListener('click', e => {
            // Ignore clicks originating from action area
            if (e.target.closest('.event-actions')) return;

            const title = card.getAttribute('data-title') || 'Event';
            const desc = card.getAttribute('data-desc') || '';
            const location = card.getAttribute('data-location') || '';
            const date = card.getAttribute('data-date') || '';
            const startDate = card.getAttribute('data-start-date') || date;
            const endDate = card.getAttribute('data-end-date') || date;


            // Set modal content
            modalEl.querySelector('#eventModalTitle').textContent = title;
            const descEl = modalEl.querySelector('#eventModalDesc');
            // Use textContent with white-space: pre-line CSS to preserve line breaks
            descEl.textContent = desc;

            // Handle "See More" functionality for long descriptions
            const seeMoreBtn = modalEl.querySelector('#eventSeeMoreBtn');
            if (desc.length > 300) {
                descEl.style.maxHeight = '200px';
                seeMoreBtn.classList.remove('d-none');
            } else {
                descEl.style.maxHeight = 'none';
                seeMoreBtn.classList.add('d-none');
            }

            // Handle location
            const locationEl = modalEl.querySelector('#eventModalLocation');
            const locationTextEl = modalEl.querySelector('#eventLocationText');
            if (location) {
                locationTextEl.textContent = location;
                locationEl.classList.remove('d-none');
            } else {
                locationEl.classList.add('d-none');
            }

            // Handle date formatting
            const dateEl = modalEl.querySelector('#eventModalDate');
            const timeEl = modalEl.querySelector('#eventModalTime');
            const timeSectionEl = modalEl.querySelector('#eventTimeSection');
            
            if (startDate === endDate) {
                // Single day event
                const dateObj = new Date(startDate);
                const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                dateEl.textContent = formattedDate;
                timeSectionEl.classList.add('d-none'); // Hide time section for now since we don't have time data
            } else {
                // Multi-day event
                const startDateObj = new Date(startDate);
                const endDateObj = new Date(endDate);
                const formattedStartDate = startDateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                const formattedEndDate = endDateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                dateEl.textContent = formattedStartDate + ' - ' + formattedEndDate;
                timeSectionEl.classList.add('d-none'); // Hide time section for now since we don't have time data
            }

            modal.show();
        });
    });

    // Prevent dropdown clicks from triggering card click
    document.querySelectorAll('.event-action-toggle').forEach(btn => {
        btn.addEventListener('click', e => e.stopPropagation());
    });
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.addEventListener('click', e => e.stopPropagation());
    });



    // Handle Delete Event button clicks
    let deleteEventId = null;
    document.querySelectorAll('.delete-event-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            deleteEventId = this.getAttribute('data-id');
            const eventTitle = this.getAttribute('data-title');

            // Set event title in modal
            document.getElementById('deleteEventTitle').textContent = eventTitle;

            // Show delete confirmation modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
            deleteModal.show();
        });
    });



    // Handle Delete Confirmation
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (!deleteEventId) return;

            const formData = new FormData();
            formData.append('event_id', deleteEventId);

            this.disabled = true;
            this.textContent = 'Deleting...';

            fetch(`backend/afprotechs_delete_event.php?event_id=${deleteEventId}`, {
                method: 'GET'
            })
                .then(async response => {
                    const text = await response.text();
                    
                    // Clean the response text by extracting JSON from it
                    let cleanText = text.trim();
                    
                    // If response contains JSON, extract it
                    const jsonMatch = cleanText.match(/\{.*\}/s);
                    if (jsonMatch) {
                        cleanText = jsonMatch[0];
                    }
                    
                    // Try to parse the cleaned JSON
                    try {
                        const data = JSON.parse(cleanText);
                        // Trust the data.success value from the parsed JSON
                        return { success: data.success === true, data: data, rawText: text };
                    } catch (e) {
                        // If JSON parsing still fails, check for success indicators in the text
                        const hasSuccessIndicator = text.includes('"success":true') || text.includes('deleted successfully');
                        const hasErrorIndicator = text.includes('"success":false') || text.includes('error') || text.includes('failed');
                        
                        // Prioritize success indicators over error indicators
                        const isSuccess = hasSuccessIndicator && !hasErrorIndicator;
                        
                        return { 
                            success: isSuccess, 
                            data: { 
                                success: isSuccess, 
                                message: isSuccess ? 'Event deleted successfully' : 'Invalid response format' 
                            },
                            rawText: text 
                        };
                    }
                })
                .then(result => {
                    console.log('Delete response:', result.data); // Debug logging
                    
                    // Always check for success first, regardless of parsing issues
                    const isActualSuccess = result.success === true || 
                                          (result.data && result.data.success === true) ||
                                          (result.data && result.data.message && result.data.message.toLowerCase().includes('deleted successfully'));
                    
                    if (isActualSuccess) {
                        // Close modal first
                        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteEventModal'));
                        if (deleteModal) {
                            deleteModal.hide();
                        }
                        
                        // Wait for modal to close before showing alert
                        setTimeout(() => {
                            alert('Event deleted successfully!');
                            location.reload();
                        }, 300);
                    } else {
                        // Only show error if it's genuinely an error (not a success message)
                        const errorMessage = result.data.message || 'Failed to delete event';
                        
                        // Double-check: don't show error if message contains success indicators
                        if (!errorMessage.toLowerCase().includes('success') && 
                            !errorMessage.toLowerCase().includes('deleted')) {
                            alert('Error: ' + errorMessage);
                        } else {
                            // It's actually a success, treat it as such
                            setTimeout(() => {
                                alert(errorMessage);
                                location.reload();
                            }, 300);
                        }
                        
                        confirmDeleteBtn.disabled = false;
                        confirmDeleteBtn.textContent = 'Yes';
                    }
                })
                .catch(error => {
                    // Only show error for genuine network failures
                    console.error('Network error:', error);
                    alert('Network connection error. Please check your internet connection and try again.');
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.textContent = 'Yes';
                });
        });
    }

    // Handle Edit Event button clicks
    document.querySelectorAll('.edit-event-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const eventId = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const desc = this.getAttribute('data-desc');
            const startDate = this.getAttribute('data-start-date');
            const endDate = this.getAttribute('data-end-date');

            const location = this.getAttribute('data-location') || '';

            // Debug logging
            console.log('Edit Event Data:');
            console.log('- Event ID:', eventId);
            console.log('- Start Date:', startDate);
            console.log('- End Date:', endDate);

            // Store original values for comparison
            const form = document.getElementById('createEventForm');
            form.setAttribute('data-original-title', title);
            form.setAttribute('data-original-desc', desc);
            form.setAttribute('data-original-start-date', startDate);
            form.setAttribute('data-original-end-date', endDate);

            form.setAttribute('data-original-location', location);

            // Populate form with proper date formatting
            document.getElementById('editEventId').value = eventId;
            document.getElementById('editEventTitle').value = title;
            document.getElementById('editEventDescription').value = desc;
            document.getElementById('editStartDate').value = startDate;
            document.getElementById('editEndDate').value = endDate;

            document.getElementById('editEventLocation').value = location;

            // Update min attribute for end date
            const endDateInput = document.getElementById('editEndDate');
            endDateInput.setAttribute('min', startDate);

            console.log('Form populated - Start Date Input:', document.getElementById('editStartDate').value);
            console.log('Form populated - End Date Input:', document.getElementById('editEndDate').value);

            // Change modal title and button text
            document.getElementById('eventModalFormTitle').textContent = 'Edit Event';
            document.getElementById('eventFormSubmitBtn').textContent = 'Update Event';

            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('createEventModal'));
            editModal.show();
        });
    });

    // Add logic to ensure end_date is at least equal to start_date
    const startDateInput = document.getElementById('editStartDate');
    const endDateInput = document.getElementById('editEndDate');

    if (startDateInput && endDateInput) {
        // Refine logic to ensure end_date is only updated when necessary
        startDateInput.addEventListener('change', function () {
            if (this.value) {
                // Set minimum date for end date input
                endDateInput.setAttribute('min', this.value);

                // Only auto-set end_date if it's empty or earlier than start_date
                const startDateObj = new Date(this.value);
                const endDateObj = new Date(endDateInput.value);

                if (!endDateInput.value) {
                    // If end date is empty, set it to start date
                    endDateInput.value = this.value;
                    console.log('Auto-set end date to start date:', this.value);
                } else if (endDateObj.getTime() < startDateObj.getTime()) {
                    // If end date is before start date, adjust it to start date
                    endDateInput.value = this.value;
                    console.log('End date was before start date, adjusted to:', this.value);
                }
            }
        });


        // Ensure end_date is not earlier than start_date
        endDateInput.addEventListener('change', function () {
            if (startDateInput.value && this.value) {
                const startDateObj = new Date(startDateInput.value);
                const endDateObj = new Date(this.value);

                if (endDateObj.getTime() < startDateObj.getTime()) {
                    alert('End date cannot be before start date. Setting to start date.');
                    this.value = startDateInput.value;
                    console.log('End date reset to start date:', startDateInput.value);
                }
            }
        });
    }

    // Handle Create Event form submission
    const createEventForm = document.getElementById('createEventForm');
    if (createEventForm) {
        createEventForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            const eventId = formData.get('event_id');
            const isEdit = eventId && eventId !== '';

            // Validate dates
            const startDate = formData.get('start_date');
            const endDate = formData.get('end_date');

            // Debug: Log the dates received
            console.log('Date validation - Start:', startDate, 'End:', endDate);
            console.log('Form data:', {
                event_id: eventId,
                event_title: formData.get('event_title'),
                start_date: startDate,
                end_date: endDate
            });

            // Extra safety check - ensure end date field is not empty
            const endDateInput = document.getElementById('editEndDate');
            const startDateInput = document.getElementById('editStartDate');
            if (startDateInput.value && !endDateInput.value) {
                // Only auto-fill if end date is completely empty
                endDateInput.value = startDateInput.value;
                console.log('End date was empty, auto-filled with start date');
            }

            if (!startDate || !endDate) {
                alert('Please fill in both start date and end date!');
                return;
            }

            if (startDate && endDate) {
                const startDateObj = new Date(startDate + 'T00:00:00');
                const endDateObj = new Date(endDate + 'T00:00:00');

                if (endDateObj < startDateObj) {
                    alert('End date cannot be before start date!');
                    return;
                }
            }

            // Log form data for debugging
            console.log('Form Data Being Sent:');
            console.log('- Event ID:', eventId || 'NEW EVENT');
            console.log('- Title:', formData.get('event_title'));
            console.log('- Start Date:', startDate);
            console.log('- End Date:', endDate);

            console.log('- Location:', formData.get('event_location'));

            // Check if editing and no changes were made
            if (isEdit) {
                const currentTitle = formData.get('event_title');
                const currentDesc = formData.get('event_description');
                const currentStartDate = formData.get('start_date');
                const currentEndDate = formData.get('end_date');

                const currentLocation = formData.get('event_location') || '';

                const originalTitle = this.getAttribute('data-original-title');
                const originalDesc = this.getAttribute('data-original-desc');
                const originalStartDate = this.getAttribute('data-original-start-date');
                const originalEndDate = this.getAttribute('data-original-end-date');

                const originalLocation = this.getAttribute('data-original-location') || '';

                // Check if any changes were made
                const hasChanges =
                    currentTitle !== originalTitle ||
                    currentDesc !== originalDesc ||
                    currentStartDate !== originalStartDate ||
                    currentEndDate !== originalEndDate ||

                    currentLocation !== originalLocation;

                if (!hasChanges) {
                    alert('No changes were made.');
                    return;
                }

                // Log what changed
                console.log('Changes detected:');
                if (currentStartDate !== originalStartDate) {
                    console.log('- Start Date changed from', originalStartDate, 'to', currentStartDate);
                }
                if (currentEndDate !== originalEndDate) {
                    console.log('- End Date changed from', originalEndDate, 'to', currentEndDate);
                }
            }

            const apiUrl = isEdit ? 'backend/afprotechs_update_event.php' : 'create_event.php';

            submitBtn.disabled = true;
            submitBtn.textContent = isEdit ? 'Updating...' : 'Creating...';

            // Choose the correct request format based on endpoint
            let requestOptions = {
                method: 'POST'
            };
            
            if (isEdit) {
                // For updates, send JSON to backend API
                const eventLocation = formData.get('event_location');
                requestOptions.headers = { 'Content-Type': 'application/json' };
                requestOptions.body = JSON.stringify({
                    event_id: eventId,
                    event_title: formData.get('event_title'),
                    event_description: formData.get('event_description'),
                    start_date: startDate,
                    end_date: endDate,
                    event_location: eventLocation
                });
            } else {
                // For creates, send FormData
                requestOptions.body = formData;
            }

            fetch(apiUrl, requestOptions)
                .then(async response => {
                    const text = await response.text();
                    
                    // Clean the response text by extracting JSON from it
                    let cleanText = text.trim();
                    
                    // If response contains JSON, extract it
                    const jsonMatch = cleanText.match(/\{.*\}/s);
                    if (jsonMatch) {
                        cleanText = jsonMatch[0];
                    }
                    
                    // Try to parse the cleaned JSON
                    try {
                        const data = JSON.parse(cleanText);
                        // Trust the data.success value from the parsed JSON
                        return { success: data.success === true, data: data, rawText: text };
                    } catch (e) {
                        // If JSON parsing still fails, check for success indicators in the text
                        const hasSuccessIndicator = text.includes('"success":true') || text.includes('successfully') || text.includes('created') || text.includes('updated');
                        const hasErrorIndicator = text.includes('"success":false') || text.includes('error') || text.includes('failed');
                        
                        // Prioritize success indicators over error indicators
                        const isSuccess = hasSuccessIndicator && !hasErrorIndicator;
                        
                        return { 
                            success: isSuccess, 
                            data: { 
                                success: isSuccess, 
                                message: isSuccess ? 'Operation completed successfully' : 'Invalid response format' 
                            },
                            rawText: text 
                        };
                    }
                })
                .then(result => {
                    console.log('Server response:', result.data); // Debug logging
                    console.log('Raw response text:', result.rawText); // Debug raw response
                    console.log('Parsed success:', result.success); // Debug success flag
                    
                    // Always check for success first, regardless of parsing issues
                    const isActualSuccess = result.success === true || 
                                          (result.data && result.data.success === true) ||
                                          (result.data && result.data.message && result.data.message.toLowerCase().includes('success'));
                    
                    if (isActualSuccess) {
                        // Show success message
                        const successMessage = isEdit ? 'Event updated successfully!' : 'Event created successfully!';

                        // Close modal first
                        const createModal = bootstrap.Modal.getInstance(document.getElementById('createEventModal'));
                        if (createModal) {
                            createModal.hide();
                        }

                        // Reset form
                        createEventForm.reset();
                        document.getElementById('editEventId').value = '';
                        document.getElementById('eventModalFormTitle').textContent = 'Create Event';
                        document.getElementById('eventFormSubmitBtn').textContent = 'Create Event';

                        // Show success message after modal closes
                        setTimeout(() => {
                            alert(successMessage);
                            location.reload();
                        }, 300);
                    } else {
                        // Only show error if it's genuinely an error (not a success message)
                        const errorMessage = result.data.message || 'Failed to save event';
                        
                        // Double-check: don't show error if message contains success indicators
                        if (!errorMessage.toLowerCase().includes('success') && 
                            !errorMessage.toLowerCase().includes('updated') && 
                            !errorMessage.toLowerCase().includes('created')) {
                            console.error('Error from server:', result.data);
                            console.error('Full result object:', result);
                            alert('Debug - Error: ' + errorMessage + '\nRaw response: ' + (result.rawText ? result.rawText.substring(0, 200) : 'No raw text'));
                        } else {
                            // It's actually a success, treat it as such
                            console.log('Success detected in error message:', errorMessage);
                            setTimeout(() => {
                                alert(errorMessage);
                                location.reload();
                            }, 300);
                        }
                        
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                })
                .catch(error => {
                    // Only show error for genuine network failures
                    console.error('Network error:', error);
                    alert('Network connection error. Please check your internet connection and try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                })
                ;
        });

        // Reset modal when closed (for create mode)
        const createModal = document.getElementById('createEventModal');
        if (createModal) {
            createModal.addEventListener('hidden.bs.modal', function () {
                createEventForm.reset();
                document.getElementById('editEventId').value = '';
                document.getElementById('editStartDate').value = '';
                document.getElementById('editEndDate').value = '';
                document.getElementById('eventModalFormTitle').textContent = 'Create Event';
                document.getElementById('eventFormSubmitBtn').textContent = 'Create Event';
            });
        }
    }
});



// Event "See More" functionality
function toggleEventSeeMore() {
    const contentEl = document.getElementById('eventModalDesc');
    const seeMoreBtn = document.getElementById('eventSeeMoreBtn');
    const icon = document.getElementById('eventSeeMoreIcon');
    
    if (contentEl.style.maxHeight === '200px') {
        // Expand
        contentEl.style.maxHeight = 'none';
        seeMoreBtn.innerHTML = '<i class="fa-solid fa-chevron-up me-1" id="eventSeeMoreIcon"></i> See Less';
    } else {
        // Collapse
        contentEl.style.maxHeight = '200px';
        seeMoreBtn.innerHTML = '<i class="fa-solid fa-chevron-down me-1" id="eventSeeMoreIcon"></i> See More';
    }
}