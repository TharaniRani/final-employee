/**
 * Form State Management
 * Centralized state management for the wizard form
 */
window.HRMS_FormState = (function() {
    'use strict';

    const config = window.HRMS_CONFIG;

    // Initial state structure
    const initialState = {
        employee_id: null,
        mobile_verified: false,
        verified_mobile: null,
        personal_basic: {
            full_name: { original_value: '', verification: 'pending', corrected_value: null },
            email: { original_value: '', verification: 'pending', corrected_value: null },
            mobile_number: { original_value: '', verification: 'pending', corrected_value: null },
            is_alumni: '',
            alumni_school: '',
            resume_file_id: null
        },
        address: {
            current: {
                address_line1: { original_value: '', verification: 'pending', corrected_value: null },
                address_line2: { original_value: '', verification: 'pending', corrected_value: null },
                address_line3: { original_value: '', verification: 'pending', corrected_value: null },
                city: { original_value: '', verification: 'pending', corrected_value: null },
                pincode: { original_value: '', verification: 'pending', corrected_value: null },
                state: { original_value: '', verification: 'pending', corrected_value: null },
                country: { original_value: 'India', verification: 'pending', corrected_value: null }
            },
            same_as_permanent: { original_value: '', verification: 'pending', corrected_value: null },
            permanent: {
                address_line1: { original_value: '', verification: 'pending', corrected_value: null },
                address_line2: { original_value: '', verification: 'pending', corrected_value: null },
                address_line3: { original_value: '', verification: 'pending', corrected_value: null },
                city: { original_value: '', verification: 'pending', corrected_value: null },
                pincode: { original_value: '', verification: 'pending', corrected_value: null },
                state: { original_value: '', verification: 'pending', corrected_value: null },
                country: { original_value: '', verification: 'pending', corrected_value: null }
            }
        },
        aadhaar_pan: {
            aadhaar: {
                name: { original_value: '', verification: 'pending', corrected_value: null },
                number: { original_value: '', verification: 'pending', corrected_value: null },
                file_id: null
            },
            pan: {
                number: { original_value: '', verification: 'pending', corrected_value: null },
                file_id: null
            },
            aadhaar_pan_linked: { original_value: '', verification: 'pending', corrected_value: null }
        },
        personal_details: {
            photo_file_id: null,
            signature_file_id: null,
            date_of_birth: { original_value: '', verification: 'pending', corrected_value: null },
            gender: { original_value: '', verification: 'pending', corrected_value: null },
            marital_status: { original_value: '', verification: 'pending', corrected_value: null },
            spouse_name: { original_value: '', verification: 'pending', corrected_value: null },
            father_name: { original_value: '', verification: 'pending', corrected_value: null },
            mother_name: { original_value: '', verification: 'pending', corrected_value: null },
            blood_group: { original_value: '', verification: 'pending', corrected_value: null }
        },
        next_of_kin: {
            name_source: { original_value: '', verification: 'pending', corrected_value: null },
            selected_name: { original_value: '', verification: 'pending', corrected_value: null },
            manual_name: { original_value: '', verification: 'pending', corrected_value: null },
            contact: { original_value: '', verification: 'pending', corrected_value: null },
            is_emergency: { original_value: '', verification: 'pending', corrected_value: null },
            emergency_contact: { original_value: '', verification: 'pending', corrected_value: null },
            address: {
                address_line1: { original_value: '', verification: 'pending', corrected_value: null },
                address_line2: { original_value: '', verification: 'pending', corrected_value: null },
                address_line3: { original_value: '', verification: 'pending', corrected_value: null },
                city: { original_value: '', verification: 'pending', corrected_value: null },
                pincode: { original_value: '', verification: 'pending', corrected_value: null },
                state: { original_value: '', verification: 'pending', corrected_value: null },
                country: { original_value: 'India', verification: 'pending', corrected_value: null }
            }
        },
        referral: {
            referred_by_name: { original_value: '', verification: 'pending', corrected_value: null },
            // referral_source: { original_value: '', verification: 'pending', corrected_value: null },
            family_working: { original_value: '', verification: 'pending', corrected_value: null },
            family_working_details: [],
            family_studying: { original_value: '', verification: 'pending', corrected_value: null },
            family_studying_details: []
        },
        education: [],
        teacher_documents: {
            is_teacher: { original_value: '', verification: 'pending', corrected_value: null },
            bed_status: { original_value: '', verification: 'pending', corrected_value: null },
            bed_institution: { original_value: '', verification: 'pending', corrected_value: null },
            bed_file_id: null,
            bed_proposed_year: { original_value: '', verification: 'pending', corrected_value: null },
            bed_graduation_year: { original_value: '', verification: 'pending', corrected_value: null },
            driving_license_file_id: null,
            police_verification_file_id: null,
            other_documents: []
        },
        work_experience: {
            not_applicable: false,
            entries: []
        },
        employment_details: {
            unit: { original_value: '', verification: 'pending', corrected_value: null, change: '', updated_value: '' },
            campus: { original_value: '', verification: 'pending', corrected_value: null, change: '', updated_value: '' },
            designation: { original_value: '', verification: 'pending', corrected_value: null, change: '', updated_value: '' },
            employee_type: { original_value: '', verification: 'pending', corrected_value: null, change: '', updated_value: '' },
            job_type: { original_value: '', verification: 'pending', corrected_value: null, change: '', updated_value: '' },
            reporting_to: { original_value: '', verification: 'pending', corrected_value: null, change: '', updated_value: '' },
            date_of_joining: { original_value: '', verification: 'pending', corrected_value: null, change: '', updated_value: '' }
        },
        submission: {
            status: 'draft',
            declaration: false,
            terms_accepted: false,
            submitted_at: null,
            reference_number: null
        },
        _meta: {
            created_at: null,
            updated_at: new Date().toISOString(),
            version: 1
        }
    };

    let state = { ...initialState };
    let listeners = new Set();
    let isDirty = false;

    function generateId(prefix = 'item') {
        return `${prefix}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    }

    function deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    function notifyListeners(path, value) {
        listeners.forEach(listener => {
            try {
                listener(path, value, state);
            } catch (error) {
                console.error('Listener error:', error);
            }
        });
    }

    function setPath(obj, path, value) {
        const keys = path.split('.');
        let current = obj;
        for (let i = 0; i < keys.length - 1; i++) {
            if (!(keys[i] in current)) {
                current[keys[i]] = isNaN(keys[i + 1]) ? {} : [];
            }
            current = current[keys[i]];
        }
        current[keys[keys.length - 1]] = value;
    }

    function getPath(obj, path) {
        const keys = path.split('.');
        let current = obj;
        for (const key of keys) {
            if (current === null || current === undefined) return undefined;
            current = current[key];
        }
        return current;
    }

    function markDirty() {
        isDirty = true;
        state._meta.updated_at = new Date().toISOString();
    }

    const formFieldPaths = {
        full_name: 'personal_basic.full_name',
        email: 'personal_basic.email',
        mobile_number: 'personal_basic.mobile_number',
        mobile: 'personal_basic.mobile_number',
        is_alumni: 'personal_basic.is_alumni',
        alumni_school: 'personal_basic.alumni_school',
        curr_address_line1: 'address.current.address_line1',
        curr_address_line2: 'address.current.address_line2',
        curr_address_line3: 'address.current.address_line3',
        curr_city: 'address.current.city',
        curr_pincode: 'address.current.pincode',
        curr_state: 'address.current.state',
        curr_country: 'address.current.country',
        same_as_permanent: 'address.same_as_permanent',
        perm_address_line1: 'address.permanent.address_line1',
        perm_address_line2: 'address.permanent.address_line2',
        perm_address_line3: 'address.permanent.address_line3',
        perm_city: 'address.permanent.city',
        perm_pincode: 'address.permanent.pincode',
        perm_state: 'address.permanent.state',
        perm_country: 'address.permanent.country',
        aadhaar_name: 'aadhaar_pan.aadhaar.name',
        aadhaar_number: 'aadhaar_pan.aadhaar.number',
        pan_number: 'aadhaar_pan.pan.number',
        aadhaar_pan_linked: 'aadhaar_pan.aadhaar_pan_linked',
        date_of_birth: 'personal_details.date_of_birth',
        dob: 'personal_details.date_of_birth',
        gender: 'personal_details.gender',
        marital_status: 'personal_details.marital_status',
        spouse_name: 'personal_details.spouse_name',
        father_name: 'personal_details.father_name',
        mother_name: 'personal_details.mother_name',
        blood_group: 'personal_details.blood_group',
        nok_name_source: 'next_of_kin.name_source',
        nok_select: 'next_of_kin.selected_name',
        nok_manual_name: 'next_of_kin.manual_name',
        nok_contact: 'next_of_kin.contact',
        nok_is_emergency: 'next_of_kin.is_emergency',
        nok_emergency_contact: 'next_of_kin.emergency_contact',
        nok_address_line1: 'next_of_kin.address.address_line1',
        nok_address_line2: 'next_of_kin.address.address_line2',
        nok_address_line3: 'next_of_kin.address.address_line3',
        nok_city: 'next_of_kin.address.city',
        nok_pincode: 'next_of_kin.address.pincode',
        nok_state: 'next_of_kin.address.state',
        nok_country: 'next_of_kin.address.country',
        referred_by_name: 'referral.referred_by_name',
        // referral_source: 'referral.referral_source',
        family_working: 'referral.family_working',
        family_studying: 'referral.family_studying',
        is_teacher: 'teacher_documents.is_teacher',
        bed_status: 'teacher_documents.bed_status',
        bed_institution: 'teacher_documents.bed_institution',
        bed_proposed_year: 'teacher_documents.bed_proposed_year',
        bed_graduation_year: 'teacher_documents.bed_graduation_year',
        resume_file_id: 'personal_basic.resume_file_id',
        aadhaar_file_id: 'aadhaar_pan.aadhaar.file_id',
        pan_file_id: 'aadhaar_pan.pan.file_id',
        photo_file_id: 'personal_details.photo_file_id',
        signature_file_id: 'personal_details.signature_file_id',
        bed_file_id: 'teacher_documents.bed_file_id',
        driving_license_file_id: 'teacher_documents.driving_license_file_id',
        police_verification_file_id: 'teacher_documents.police_verification_file_id',
        employment_unit: 'employment_details.unit',
        employment_campus: 'employment_details.campus',
        employment_designation: 'employment_details.designation',
        employment_employee_type: 'employment_details.employee_type',
        employment_job_type: 'employment_details.job_type',
        employment_reporting_to: 'employment_details.reporting_to',
        employment_date_of_joining: 'employment_details.date_of_joining',
        employment_unit_change: 'employment_details.unit.change',
        employment_campus_change: 'employment_details.campus.change',
        employment_designation_change: 'employment_details.designation.change',
        employment_employee_type_change: 'employment_details.employee_type.change',
        employment_job_type_change: 'employment_details.job_type.change',
        employment_reporting_to_change: 'employment_details.reporting_to.change',
        employment_date_of_joining_change: 'employment_details.date_of_joining.change',
        employment_unit_updated: 'employment_details.unit.updated_value',
        employment_campus_updated: 'employment_details.campus.updated_value',
        employment_designation_updated: 'employment_details.designation.updated_value',
        employment_employee_type_updated: 'employment_details.employee_type.updated_value',
        employment_job_type_updated: 'employment_details.job_type.updated_value',
        employment_reporting_to_updated: 'employment_details.reporting_to.updated_value',
        employment_date_of_joining_updated: 'employment_details.date_of_joining.updated_value',
        declaration: 'submission.declaration',
        terms_accepted: 'submission.terms_accepted'
    };

    function bindFormInputs() {
        const syncPermanentLocation = (sameAsCurrent) => {
            ['city', 'pincode', 'state', 'country'].forEach(field => {
                const permanentPath = `address.permanent.${field}`;
                const value = sameAsCurrent
                    ? window.HRMS_FormState.get(`address.current.${field}`)
                    : {
                        original_value: '',
                        verification: 'pending',
                        corrected_value: null
                    };
                window.HRMS_FormState.set(permanentPath, value);

                const control = document.querySelector(`[name="perm_${field}"]`);
                if (control) {
                    control.value = value.original_value || '';
                }

                const correctedControl = document.querySelector(`[name="perm_${field}_corrected"]`);
                if (correctedControl) {
                    correctedControl.value = value.corrected_value || '';
                }

                document.querySelectorAll(`[name="perm_${field}_verification"]`).forEach(radio => {
                    radio.checked = radio.value === value.verification;
                });
            });
        };

        const syncInput = (event) => {
            const input = event.target;
            if (!(input instanceof HTMLElement) || !('name' in input)) return;

            const name = input.name;
            if (!name || name.endsWith('[]') || input instanceof HTMLInputElement && input.type === 'file') return;

            const verificationMatch = name.match(/^(.+)_verification$/);
            const correctedMatch = name.match(/^(.+)_corrected$/);
            const mappedName = verificationMatch?.[1] || correctedMatch?.[1] || name;
            const path = formFieldPaths[mappedName];
            if (!path) return;

            if ((input instanceof HTMLInputElement && input.type === 'radio') && !input.checked) return;

            const value = input instanceof HTMLInputElement && input.type === 'checkbox'
                ? input.checked
                : input.value;
            const isSimpleValue = path === 'personal_basic.is_alumni' ||
                path === 'personal_basic.alumni_school' ||
                path === 'referral.family_working' ||
                path === 'referral.family_studying' ||
                path === 'submission.declaration' ||
                path === 'submission.terms_accepted' ||
                path === 'personal_basic.resume_file_id' ||
                path === 'aadhaar_pan.aadhaar.file_id' ||
                path === 'aadhaar_pan.pan.file_id' ||
                path === 'personal_details.photo_file_id' ||
                path === 'personal_details.signature_file_id' ||
                path === 'teacher_documents.bed_file_id' ||
                path === 'teacher_documents.driving_license_file_id' ||
                path === 'teacher_documents.police_verification_file_id';
            const isEmploymentChangeField = mappedName.startsWith('employment_') &&
                (mappedName.endsWith('_change') || mappedName.endsWith('_updated'));

            if (verificationMatch) {
                window.HRMS_FormState.set(`${path}.verification`, value);
            } else if (correctedMatch) {
                window.HRMS_FormState.set(`${path}.corrected_value`, value);
            } else if (isEmploymentChangeField) {
                window.HRMS_FormState.set(path, value);
            } else if (isSimpleValue) {
                window.HRMS_FormState.set(path, value);
            } else {
                window.HRMS_FormState.set(`${path}.original_value`, value);
            }

            if (mappedName === 'same_as_permanent') {
                syncPermanentLocation(value === 'Yes');
            } else if (
                ['curr_city', 'curr_pincode', 'curr_state', 'curr_country'].includes(mappedName) &&
                window.HRMS_FormState.get('address.same_as_permanent.original_value') === 'Yes'
            ) {
                syncPermanentLocation(true);
            }
        };

        document.addEventListener('input', syncInput, true);
        document.addEventListener('change', syncInput, true);
    }

    return {
        bindFormInputs,

        getFieldStatePath(name) {
            return formFieldPaths[name] || null;
        },

        getState() {
            return deepClone(state);
        },

        get(path) {
            return deepClone(getPath(state, path));
        },

        set(path, value) {
            const oldValue = getPath(state, path);
            setPath(state, path, deepClone(value));
            markDirty();
            notifyListeners(path, value);
        },

        update(path, updater) {
            const current = getPath(state, path);
            const newValue = updater(deepClone(current));
            this.set(path, newValue);
        },

        subscribe(listener) {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },

        // Education management
        addEducation(entry = {}) {
            const newEntry = {
                id: generateId('edu'),
                qualification_type: { original_value: '', verification: 'pending', corrected_value: null },
                qualification_name: { original_value: '', verification: 'pending', corrected_value: null },
                skilled_in: { original_value: '', verification: 'pending', corrected_value: null },
                special_talents: { original_value: '', verification: 'pending', corrected_value: null },
                institution: { original_value: '', verification: 'pending', corrected_value: null },
                year_of_completion: { original_value: '', verification: 'pending', corrected_value: null },
                certificate_file_id: null,
                ...entry
            };
            state.education.push(newEntry);
            markDirty();
            notifyListeners('education', state.education);
            return newEntry;
        },

        removeEducation(id) {
            const index = state.education.findIndex(e => e.id === id);
            if (index !== -1) {
                state.education.splice(index, 1);
                markDirty();
                notifyListeners('education', state.education);
                return true;
            }
            return false;
        },

        updateEducation(id, updates) {
            const index = state.education.findIndex(e => e.id === id);
            if (index === -1) return;

            const entry = state.education[index];
            Object.assign(entry, updates);
            markDirty();
            notifyListeners(`education[${index}]`, entry);
        },

        // Work Experience management
        addExperience(entry = {}) {
            const newEntry = {
                id: generateId('exp'),
                company: { original_value: '', verification: 'pending', corrected_value: null },
                designation: { original_value: '', verification: 'pending', corrected_value: null },
                start_month: { original_value: '', verification: 'pending', corrected_value: null },
                end_month: { original_value: '', verification: 'pending', corrected_value: null },
                details: { original_value: '', verification: 'pending', corrected_value: null },
                experience_letter_file_id: null,
                pay_slip_file_id: null,
                ...entry
            };
            state.work_experience.entries.push(newEntry);
            markDirty();
            notifyListeners('work_experience.entries', state.work_experience.entries);
            return newEntry;
        },

        removeExperience(id) {
            const index = state.work_experience.entries.findIndex(e => e.id === id);
            if (index !== -1) {
                state.work_experience.entries.splice(index, 1);
                markDirty();
                notifyListeners('work_experience.entries', state.work_experience.entries);
                return true;
            }
            return false;
        },

        updateExperience(id, updates) {
            const index = state.work_experience.entries.findIndex(e => e.id === id);
            if (index === -1) return;

            const entry = state.work_experience.entries[index];
            Object.assign(entry, updates);
            markDirty();
            notifyListeners(`work_experience.entries[${index}]`, entry);
        },

        // Family Working management
        addFamilyWorking(entry = {}) {
            const newEntry = {
                id: generateId('fw'),
                tmss_id: { original_value: '', verification: 'pending', corrected_value: null },
                name: { original_value: '', verification: 'pending', corrected_value: null },
                unit: { original_value: '', verification: 'pending', corrected_value: null },
                designation: { original_value: '', verification: 'pending', corrected_value: null },
                ...entry
            };
            state.referral.family_working_details.push(newEntry);
            markDirty();
            notifyListeners('referral.family_working_details', state.referral.family_working_details);
            return newEntry;
        },

        removeFamilyWorking(id) {
            const index = state.referral.family_working_details.findIndex(e => e.id === id);
            if (index !== -1) {
                state.referral.family_working_details.splice(index, 1);
                markDirty();
                notifyListeners('referral.family_working_details', state.referral.family_working_details);
                return true;
            }
            return false;
        },

        updateFamilyWorking(id, updates) {
            const index = state.referral.family_working_details.findIndex(e => e.id === id);
            if (index === -1) return;

            const entry = state.referral.family_working_details[index];
            Object.assign(entry, updates);
            markDirty();
            notifyListeners(`referral.family_working_details[${index}]`, entry);
        },

        // Family Studying management
        addFamilyStudying(entry = {}) {
            const newEntry = {
                id: generateId('fs'),
                name: { original_value: '', verification: 'pending', corrected_value: null },
                school: { original_value: '', verification: 'pending', corrected_value: null },
                class: { original_value: '', verification: 'pending', corrected_value: null },
                ...entry
            };
            state.referral.family_studying_details.push(newEntry);
            markDirty();
            notifyListeners('referral.family_studying_details', state.referral.family_studying_details);
            return newEntry;
        },

        removeFamilyStudying(id) {
            const index = state.referral.family_studying_details.findIndex(e => e.id === id);
            if (index !== -1) {
                state.referral.family_studying_details.splice(index, 1);
                markDirty();
                notifyListeners('referral.family_studying_details', state.referral.family_studying_details);
                return true;
            }
            return false;
        },

        updateFamilyStudying(id, updates) {
            const index = state.referral.family_studying_details.findIndex(e => e.id === id);
            if (index === -1) return;

            const entry = state.referral.family_studying_details[index];
            Object.assign(entry, updates);
            markDirty();
            notifyListeners(`referral.family_studying_details[${index}]`, entry);
        },

        // Other Documents management
        addOtherDocument(entry = {}) {
            const newEntry = {
                id: generateId('doc'),
                title: { original_value: '', verification: 'pending', corrected_value: null },
                file_id: null,
                ...entry
            };
            state.teacher_documents.other_documents.push(newEntry);
            markDirty();
            notifyListeners('teacher_documents.other_documents', state.teacher_documents.other_documents);
            return newEntry;
        },

        updateOtherDocument(id, updates) {
            const index = state.teacher_documents.other_documents.findIndex(entry => entry.id === id);
            if (index === -1) return;

            const entry = state.teacher_documents.other_documents[index];
            Object.assign(entry, updates);
            markDirty();
            notifyListeners(`teacher_documents.other_documents[${index}]`, entry);
        },

        removeOtherDocument(id) {
            const index = state.teacher_documents.other_documents.findIndex(e => e.id === id);
            if (index !== -1) {
                state.teacher_documents.other_documents.splice(index, 1);
                markDirty();
                notifyListeners('teacher_documents.other_documents', state.teacher_documents.other_documents);
                return true;
            }
            return false;
        },

        // Verification helpers
        setVerification(path, status, correctedValue = null) {
            const fullPath = `${path}.verification`;
            this.set(fullPath, status);
            if (correctedValue !== null) {
                this.set(`${path}.corrected_value`, correctedValue);
            }
        },

        // Draft management
        loadDraft(draftData) {
            state = deepClone(draftData);
            state._meta = {
                ...deepClone(initialState._meta),
                ...(state._meta || {}),
                updated_at: new Date().toISOString()
            };
            isDirty = false;
            notifyListeners('*', state);
        },

        reset() {
            state = deepClone(initialState);
            isDirty = false;
            notifyListeners('*', state);
        },

        isDirty() {
            return isDirty;
        },

        markClean() {
            isDirty = false;
        },

        // Export for submission
        toJSON() {
            const cleanState = deepClone(state);
            // Remove internal fields
            delete cleanState._meta;
            return cleanState;
        },

        // Initialize from employee data
        initFromEmployee(employeeData) {
            if (!employeeData) return;

            const setOriginal = (path, value) => {
                if (value !== undefined) {
                    this.set(`${path}.original_value`, value === null ? '' : String(value));
                }
            };
            const makeVerifiedField = value => ({
                original_value: value === null || value === undefined ? '' : String(value),
                verification: 'pending',
                corrected_value: null
            });
            const documents = Array.isArray(employeeData.documents) ? employeeData.documents : [];
            const findDocumentIds = types => documents
                .filter(document => types.includes(String(document.document_type || '').toLowerCase().replace(/-/g, '_')))
                .map(document => String(document.id));
            const resumeIds = findDocumentIds(['resume']);
            const aadhaarIds = findDocumentIds(['aadhaar', 'aadhar']);
            const panIds = findDocumentIds(['pan']);
            const photoIds = findDocumentIds(['photo']);
            const signatureIds = findDocumentIds(['signature', 'staff_sign']);
            const bedIds = findDocumentIds(['bed']);
            const drivingIds = findDocumentIds(['driving', 'driving_license']);
            const policeIds = findDocumentIds(['police_verification']);

            setOriginal('personal_basic.full_name', employeeData.full_name);
            setOriginal('personal_basic.email', employeeData.email);
            setOriginal('personal_basic.mobile_number', employeeData.mobile);
            if (employeeData.mobile !== undefined && employeeData.mobile !== null && employeeData.mobile !== '') {
                this.set('personal_basic.mobile_number.verification', 'correct');
                this.set('verified_mobile', employeeData.mobile);
                this.set('mobile_verified', true);
            }
            if (employeeData.employee_id !== undefined && employeeData.employee_id !== null) {
                this.set('employee_id', employeeData.employee_id);
            }

            const employeeFieldMap = {
                date_of_birth: 'personal_details.date_of_birth',
                gender: 'personal_details.gender',
                marital_status: 'personal_details.marital_status',
                spouse_name: 'personal_details.spouse_name',
                father_name: 'personal_details.father_name',
                mother_name: 'personal_details.mother_name',
                blood_group: 'personal_details.blood_group',
                aadhaar_number: 'aadhaar_pan.aadhaar.number',
                pan_number: 'aadhaar_pan.pan.number',
                aadhaar_pan_linked: 'aadhaar_pan.aadhaar_pan_linked',
                current_address: 'address.current.address_line1',
                current_address_line2: 'address.current.address_line2',
                current_address_line3: 'address.current.address_line3',
                current_city: 'address.current.city',
                current_pincode: 'address.current.pincode',
                current_state: 'address.current.state',
                current_country: 'address.current.country',
                permanent_address: 'address.permanent.address_line1',
                permanent_address_line2: 'address.permanent.address_line2',
                permanent_address_line3: 'address.permanent.address_line3',
                is_teacher: 'teacher_documents.is_teacher',
                bed_status: 'teacher_documents.bed_status',
                bed_institution: 'teacher_documents.bed_institution',
                bed_proposed_year: 'teacher_documents.bed_proposed_year',
                bed_graduation_year: 'teacher_documents.bed_graduation_year',
                employment_unit: 'employment_details.unit',
                employment_campus: 'employment_details.campus',
                employment_designation: 'employment_details.designation',
                employment_employee_type: 'employment_details.employee_type',
                employment_job_type: 'employment_details.job_type',
                employment_reporting_to: 'employment_details.reporting_to',
                employment_date_of_joining: 'employment_details.date_of_joining'
            };

            Object.entries(employeeFieldMap).forEach(([employeeField, statePath]) => {
                const value = employeeData[employeeField];
                if (value !== null && value !== undefined && value !== '') {
                    setOriginal(statePath, value);
                }
            });

            setOriginal('aadhaar_pan.aadhaar.name', employeeData.aadhaar_name || employeeData.full_name);
            setOriginal('next_of_kin.name_source', employeeData.nok_name_source);
            setOriginal('next_of_kin.selected_name', employeeData.nok_selected_name);
            setOriginal('next_of_kin.manual_name', employeeData.nok_manual_name);
            setOriginal('next_of_kin.contact', employeeData.nok_contact);
            setOriginal('next_of_kin.is_emergency', employeeData.nok_is_emergency);
            setOriginal('next_of_kin.emergency_contact', employeeData.nok_emergency_contact);
            setOriginal('next_of_kin.address.address_line1', employeeData.nok_address);
            setOriginal('next_of_kin.address.address_line2', employeeData.nok_address_line2);
            setOriginal('next_of_kin.address.address_line3', employeeData.nok_address_line3);
            setOriginal('next_of_kin.address.city', employeeData.nok_city);
            setOriginal('next_of_kin.address.pincode', employeeData.nok_pincode);
            setOriginal('next_of_kin.address.state', employeeData.nok_state);
            setOriginal('next_of_kin.address.country', employeeData.nok_country);
            setOriginal('referral.referred_by_name', employeeData.referred_by);
            // setOriginal('referral.referral_source', employeeData.referral_source);
            setOriginal('referral.family_working', employeeData.family_working);
            setOriginal('referral.family_studying', employeeData.family_studying);
            this.set('address.same_as_permanent.original_value', employeeData.same_as_permanent || '');
            if (employeeData.same_as_permanent === 'Yes') {
                ['city', 'pincode', 'state', 'country'].forEach(field => {
                    this.set(
                        `address.permanent.${field}`,
                        this.get(`address.current.${field}`)
                    );
                });
            }
            if (employeeData.is_alumni !== undefined) {
                this.set('personal_basic.is_alumni', employeeData.is_alumni || '');
            }
            this.set('personal_basic.alumni_school', employeeData.alumni_school || '');

            this.set('personal_basic.resume_file_id', resumeIds[0] || null);
            this.set('aadhaar_pan.aadhaar.file_id', aadhaarIds[0] || null);
            this.set('aadhaar_pan.pan.file_id', panIds[0] || null);
            this.set('personal_details.photo_file_id', photoIds[0] || null);
            this.set('personal_details.signature_file_id', signatureIds[0] || null);
            this.set('teacher_documents.bed_file_id', bedIds[0] || null);
            this.set('teacher_documents.driving_license_file_id', drivingIds[0] || null);
            this.set('teacher_documents.police_verification_file_id', policeIds[0] || null);

            const educationCertificates = findDocumentIds(['education_certificate']);
            this.set('education', (employeeData.education || []).map((row, index) => ({
                id: `db-education-${row.id}`,
                qualification_type: makeVerifiedField(row.qualification_type),
                qualification_name: makeVerifiedField(row.qualification_details),
                skilled_in: makeVerifiedField(''),
                special_talents: makeVerifiedField(''),
                institution: makeVerifiedField(row.institution),
                year_of_completion: makeVerifiedField(row.completion_year),
                certificate_file_id: educationCertificates[index] || null,
                legacy_certificate_name: row.certificate_name || ''
            })));

            const experienceLetters = findDocumentIds(['experience_letter']);
            const experiencePayslips = findDocumentIds(['experience_payslip', 'experience_pay_slip']);
            this.set('work_experience.entries', (employeeData.work_experience || []).map((row, index) => ({
                id: `db-experience-${row.id}`,
                company: makeVerifiedField(row.company_name),
                designation: makeVerifiedField(row.role),
                start_month: makeVerifiedField(String(row.start_date || '').slice(0, 7)),
                end_month: makeVerifiedField(String(row.end_date || '').slice(0, 7)),
                details: makeVerifiedField(row.experience_details),
                experience_letter_file_id: experienceLetters[index] || null,
                pay_slip_file_id: experiencePayslips[index] || null,
                legacy_experience_letter_name: row.experience_letter_name || '',
                legacy_pay_slip_name: row.pay_slip_name || ''
            })));
            const referralRows = employeeData.referrals || [];
            const workingRows = referralRows.filter(row => String(row.working_name || '').trim() !== '');
            const studyingRows = referralRows.filter(row => String(row.student_name || '').trim() !== '');
            this.set('referral.family_working_details', workingRows.map((row, index) => ({
                id: `db-family-working-${row.referral_id || index}`,
                tmss_id: makeVerifiedField(row.working_tmss_id),
                name: makeVerifiedField(row.working_name),
                unit: makeVerifiedField(row.working_unit),
                designation: makeVerifiedField(row.working_designation)
            })));
            this.set('referral.family_studying_details', studyingRows.map((row, index) => ({
                id: `db-family-studying-${row.referral_id || index}`,
                name: makeVerifiedField(row.student_name),
                school: makeVerifiedField(row.student_unit),
                class: makeVerifiedField(row.student_class)
            })));

            const otherDocuments = documents.filter(document =>
                String(document.document_type || '').toLowerCase().replace(/-/g, '_') === 'other_document'
            );
            const legacyOtherName = employeeData.other_docname || '';
            this.set('teacher_documents.other_documents', otherDocuments.map((document, index) => ({
                id: `db-other-document-${document.id || index}`,
                title: makeVerifiedField(document.original_name || legacyOtherName),
                file_id: String(document.id)
            })));
        }
    };
})();