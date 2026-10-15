import './bootstrap';

try {
    if (localStorage.getItem('cfo-sidebar') === 'compact') {
        document.querySelector('[data-workspace]')?.classList.add('nav-collapsed');
    }
} catch {
    // The navigation still works when browser storage is unavailable.
}

document.addEventListener('DOMContentLoaded', () => {
    const studentCatalogSource = document.querySelector('[data-student-catalog]');
    const studentAccountForm = document.querySelector('[data-student-account-form]');
    if (studentCatalogSource && studentAccountForm) {
        try {
            const catalog = JSON.parse(studentCatalogSource.textContent);
            const department = studentAccountForm.querySelector('[data-student-department]');
            const course = studentAccountForm.querySelector('[data-student-course]');
            const major = studentAccountForm.querySelector('[data-student-major]');
            const majorField = studentAccountForm.querySelector('[data-student-major-field]');
            const fill = (select, values, prompt, selected = '') => {
                select.replaceChildren(new Option(prompt, ''));
                values.forEach(value => select.add(new Option(value, value, false, value === selected)));
                select.disabled = values.length === 0;
            };
            const fillMajors = (selected = '') => {
                const majors = catalog[department.value]?.[course.value] ?? [];
                fill(major, majors, 'Select major', selected);
                major.required = majors.length > 0;
                major.disabled = majors.length === 0;
                majorField.classList.toggle('is-visible', majors.length > 0);
                majorField.setAttribute('aria-hidden', String(majors.length === 0));
            };
            const fillCourses = (selectedCourse = '', selectedMajor = '') => {
                fill(course, Object.keys(catalog[department.value] ?? {}), 'Select course', selectedCourse);
                fillMajors(selectedMajor);
            };
            department.addEventListener('change', () => fillCourses());
            course.addEventListener('change', () => fillMajors());
            fillCourses(course.dataset.selected ?? '', major.dataset.selected ?? '');

            studentAccountForm.querySelector('[data-uppercase-name]')?.addEventListener('input', event => {
                event.currentTarget.value = event.currentTarget.value.toLocaleUpperCase();
            });
            studentAccountForm.querySelector('[data-uppercase-letter]')?.addEventListener('input', event => {
                event.currentTarget.value = event.currentTarget.value.replace(/[^a-z]/gi, '').slice(0, 1).toUpperCase();
            });

            const password = studentAccountForm.querySelector('[data-registration-password]');
            const confirmation = studentAccountForm.querySelector('[data-registration-password-confirm]');
            const passwordStatus = studentAccountForm.querySelector('[data-password-match-status]');
            const validatePasswordMatch = () => {
                const mismatch = confirmation.value !== '' && password.value !== confirmation.value;
                confirmation.setCustomValidity(mismatch ? 'The password and confirmation password must match.' : '');
                passwordStatus.textContent = mismatch ? 'Passwords do not match.' : (confirmation.value ? 'Passwords match.' : 'Re-enter the same password.');
            };
            password.addEventListener('input', validatePasswordMatch);
            confirmation.addEventListener('input', validatePasswordMatch);
        } catch {
            // Backend validation remains authoritative if configuration cannot be parsed.
        }
    }

    const profileCatalogSource = document.querySelector('[data-profile-academic-catalog]');
    const profileAcademicForm = document.querySelector('[data-profile-academic-form]');
    if (profileCatalogSource && profileAcademicForm) {
        try {
            const catalog = JSON.parse(profileCatalogSource.textContent);
            const department = profileAcademicForm.querySelector('[data-profile-department]');
            const course = profileAcademicForm.querySelector('[data-profile-course]');
            const major = profileAcademicForm.querySelector('[data-profile-major]');
            const majorField = profileAcademicForm.querySelector('[data-profile-major-field]');
            const fill = (select, values, prompt, selected = '') => {
                select.replaceChildren(new Option(prompt, ''));
                values.forEach(value => select.add(new Option(value, value, false, value === selected)));
                select.disabled = values.length === 0;
            };
            const fillMajors = (selected = '') => {
                const majors = catalog[department.value]?.[course.value] ?? [];
                fill(major, majors, 'Select major', selected);
                major.required = majors.length > 0;
                major.disabled = majors.length === 0;
                majorField.classList.toggle('is-visible', majors.length > 0);
                majorField.setAttribute('aria-hidden', String(majors.length === 0));
            };
            const fillCourses = (selectedCourse = '', selectedMajor = '') => {
                fill(course, Object.keys(catalog[department.value] ?? {}), 'Select course', selectedCourse);
                fillMajors(selectedMajor);
            };
            department.addEventListener('change', () => fillCourses());
            course.addEventListener('change', () => fillMajors());
            profileAcademicForm.querySelector('[data-uppercase-letter]')?.addEventListener('input', event => {
                event.currentTarget.value = event.currentTarget.value.replace(/[^a-z]/gi, '').slice(0, 1).toUpperCase();
            });
            fillCourses(course.dataset.selected ?? '', major.dataset.selected ?? '');
        } catch {
            // Backend validation remains authoritative if configuration cannot be parsed.
        }
    }

    const workspace = document.querySelector('[data-workspace]');
    const menu = document.querySelector('[data-menu]');
    const sidebar = document.querySelector('#sidebar');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const menuClosers = document.querySelectorAll('[data-menu-close]');
    const mainContent = document.querySelector('#main-content');
    const mobileQuery = window.matchMedia('(max-width: 900px)');
    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    let menuWasOpen = false;
    let sidebarAnimations = [];

    const setMenu = open => {
        open = Boolean(open && mobileQuery.matches);
        sidebar?.classList.toggle('open', open);
        workspace?.classList.toggle('menu-open', open);
        document.body.classList.toggle('nav-open', open);
        menu?.setAttribute('aria-expanded', String(open));
        menu?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        if (sidebar) {
            if (open) {
                sidebar.setAttribute('role', 'dialog');
                sidebar.setAttribute('aria-modal', 'true');
            } else {
                sidebar.removeAttribute('role');
                sidebar.removeAttribute('aria-modal');
            }
        }
        if (mainContent) mainContent.inert = open;
        if (open) {
            menuWasOpen = true;
            requestAnimationFrame(() => sidebar?.querySelector('[data-menu-close]')?.focus());
        } else if (menuWasOpen) {
            menuWasOpen = false;
            menu?.focus();
        }
    };

    menu?.addEventListener('click', () => setMenu(!sidebar?.classList.contains('open')));
    menuClosers.forEach(button => button.addEventListener('click', () => setMenu(false)));
    sidebar?.querySelectorAll('nav a').forEach(link => link.addEventListener('click', () => setMenu(false)));
    mobileQuery.addEventListener('change', () => setMenu(false));

    const focusableElements = () => [...(sidebar?.querySelectorAll('a[href], button:not(:disabled)') ?? [])]
        .filter(element => element.getClientRects().length > 0);
    document.addEventListener('keydown', event => {
        if (!sidebar?.classList.contains('open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setMenu(false);
        }
        if (event.key === 'Tab') {
            const items = focusableElements();
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });

    const updateToggle = collapsed => {
        workspace?.classList.toggle('nav-collapsed', collapsed);
        sidebarToggle?.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle?.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        if (sidebarToggle) sidebarToggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
    };
    updateToggle(Boolean(workspace?.classList.contains('nav-collapsed')));

    const sidebarMotionParts = '.brand, .sidebar-toggle, .nav-link, .sidebar-user';
    const stopSidebarMotion = () => {
        sidebarAnimations.forEach(animation => animation.cancel());
        sidebarAnimations = [];
    };
    const captureSidebarLayout = () => {
        if (!sidebar || mobileQuery.matches || reducedMotionQuery.matches) return null;
        stopSidebarMotion();
        return {
            bounds: sidebar.getBoundingClientRect(),
            radius: getComputedStyle(sidebar).borderRadius,
            items: new Map([...sidebar.querySelectorAll(sidebarMotionParts)]
                .filter(item => item.getClientRects().length > 0)
                .map(item => [item, item.getBoundingClientRect()])),
        };
    };
    const animateSidebarLayout = (before, collapsed) => {
        if (!before || !sidebar || typeof sidebar.animate !== 'function') return;
        const after = sidebar.getBoundingClientRect();
        const animate = (target, frames, delay = 0) => {
            const animation = target.animate(frames, {
                duration: 460 - delay,
                delay,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                fill: 'backwards',
            });
            sidebarAnimations.push(animation);
        };
        animate(sidebar, [
            { width: `${before.bounds.width}px`, borderRadius: before.radius },
            { width: `${after.width}px`, borderRadius: getComputedStyle(sidebar).borderRadius },
        ]);
        [...sidebar.querySelectorAll(sidebarMotionParts)]
            .filter(item => item.getClientRects().length > 0)
            .forEach(item => {
                const previous = before.items.get(item);
                if (!previous) return;
                const bounds = item.getBoundingClientRect();
                const x = previous.left - before.bounds.left - (bounds.left - after.left);
                const y = previous.top - before.bounds.top - (bounds.top - after.top);
                animate(item, [
                    { transform: `translate(${x}px, ${y}px)` },
                    { transform: 'translate(0, 0)' },
                ]);
            });
        if (!collapsed) {
            sidebar.querySelectorAll('.nav-label, .sidebar-toggle span').forEach((label, index) => {
                animate(label, [
                    { opacity: 0, transform: 'translateX(-8px)' },
                    { opacity: 1, transform: 'translateX(0)' },
                ], 90 + Math.min(index * 14, 126));
            });
        }
    };
    sidebarToggle?.addEventListener('click', () => {
        const before = captureSidebarLayout();
        const collapsed = !workspace?.classList.contains('nav-collapsed');
        updateToggle(collapsed);
        animateSidebarLayout(before, collapsed);
        try {
            localStorage.setItem('cfo-sidebar', collapsed ? 'compact' : 'expanded');
        } catch {
            // Keep the current session usable when browser storage is unavailable.
        }
    });

    const tooltip = document.createElement('div');
    tooltip.className = 'sidebar-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    document.body.append(tooltip);
    let tooltipTimer;
    const hideTooltip = () => {
        window.clearTimeout(tooltipTimer);
        tooltip.classList.remove('visible');
    };
    const showTooltip = (element, immediate = false) => {
        hideTooltip();
        if (!workspace?.classList.contains('nav-collapsed') || mobileQuery.matches) return;
        const reveal = () => {
            const rect = element.getBoundingClientRect();
            tooltip.textContent = element.dataset.label || element.getAttribute('aria-label');
            tooltip.style.top = `${rect.top + rect.height / 2}px`;
            tooltip.style.left = `${(sidebar?.getBoundingClientRect().right ?? rect.right) + 10}px`;
            tooltip.classList.add('visible');
        };
        tooltipTimer = window.setTimeout(reveal, immediate ? 0 : 180);
    };
    sidebar?.querySelectorAll('[data-label]').forEach(element => {
        element.addEventListener('mouseenter', () => showTooltip(element));
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('focus', () => showTooltip(element, true));
        element.addEventListener('blur', hideTooltip);
    });
    sidebar?.addEventListener('scroll', hideTooltip, true);
    sidebarToggle?.addEventListener('click', hideTooltip);

    const dock = sidebar?.querySelector('.animated-dock');
    const finePointerQuery = window.matchMedia('(hover: hover) and (pointer: fine)');
    const dockItems = [...(dock?.querySelectorAll('.nav-icon') ?? [])].map(icon => ({
        icon,
        scale: 1,
        velocity: 0,
        target: 1,
    }));
    let dockFrame = 0;
    let dockPrevious = 0;
    const animateDock = time => {
        const delta = Math.min((time - (dockPrevious || time - 16)) / 1000, .032);
        dockPrevious = time;
        let moving = false;
        dockItems.forEach(item => {
            item.velocity += ((item.target - item.scale) * 240 - item.velocity * 22) * delta;
            item.scale += item.velocity * delta;
            if (Math.abs(item.target - item.scale) < .001 && Math.abs(item.velocity) < .001) {
                item.scale = item.target;
                item.velocity = 0;
            } else {
                moving = true;
            }
            item.icon.style.transform = `scale(${item.scale})`;
        });
        dockFrame = moving ? requestAnimationFrame(animateDock) : 0;
        if (!moving) dockPrevious = 0;
    };
    const updateDock = pointerY => {
        dockItems.forEach(item => {
            const link = item.icon.closest('a');
            if (!link) return;
            const rect = link.getBoundingClientRect();
            const proximity = Math.max(0, 1 - Math.abs(pointerY - rect.top - rect.height / 2) / 110);
            item.target = 1 + proximity * .22;
        });
        if (!dockFrame) dockFrame = requestAnimationFrame(animateDock);
    };
    const clearDock = () => {
        cancelAnimationFrame(dockFrame);
        dockFrame = 0;
        dockPrevious = 0;
        dockItems.forEach(item => {
            item.scale = 1;
            item.velocity = 0;
            item.target = 1;
            item.icon.style.removeProperty('transform');
        });
    };
    dock?.addEventListener('pointermove', event => {
        if (!reducedMotionQuery.matches && finePointerQuery.matches && event.pointerType === 'mouse') {
            updateDock(event.clientY);
        }
    });
    dock?.addEventListener('pointerleave', () => updateDock(Infinity));
    dock?.addEventListener('focusin', event => {
        if (reducedMotionQuery.matches) return;
        const link = event.target.closest('a');
        if (!link) return;
        const rect = link.getBoundingClientRect();
        updateDock(rect.top + rect.height / 2);
    });
    dock?.addEventListener('focusout', () => updateDock(Infinity));
    reducedMotionQuery.addEventListener('change', clearDock);
    finePointerQuery.addEventListener('change', clearDock);
    window.addEventListener('resize', () => {
        stopSidebarMotion();
        clearDock();
    });

    document.querySelectorAll('[data-attendance-camera]').forEach(camera => {
        const video = camera.querySelector('[data-camera-video]');
        const canvas = camera.querySelector('[data-camera-canvas]');
        const preview = camera.querySelector('[data-camera-preview]');
        const fileInput = camera.querySelector('[data-camera-file]');
        const startButton = camera.querySelector('[data-camera-start]');
        const captureButton = camera.querySelector('[data-camera-capture]');
        const submitButton = camera.querySelector('[data-camera-submit]');
        const message = camera.querySelector('[data-camera-message]');
        let stream;
        let previewUrl;

        const stopCamera = () => {
            stream?.getTracks().forEach(track => track.stop());
            stream = undefined;
            if (video) video.srcObject = null;
        };
        const setMessage = value => { if (message) message.textContent = value; };

        startButton?.addEventListener('click', async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                setMessage('This browser cannot access a live camera. Use a current browser over HTTPS or localhost.');
                return;
            }
            try {
                stopCamera();
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                video.srcObject = stream;
                await video.play();
                video.hidden = false;
                preview.hidden = true;
                captureButton.disabled = false;
                submitButton.disabled = true;
                setMessage('Camera ready. Center your face, then take the photo.');
            } catch {
                setMessage('Camera access was not granted. Allow camera access in the browser and try again.');
            }
        });

        captureButton?.addEventListener('click', () => {
            if (!stream || !video.videoWidth || !video.videoHeight) return;
            const maxWidth = 1280;
            const scale = Math.min(1, maxWidth / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(blob => {
                if (!blob) {
                    setMessage('The photo could not be captured. Please try again.');
                    return;
                }
                const transfer = new DataTransfer();
                transfer.items.add(new File([blob], `staff-attendance-${Date.now()}.jpg`, { type: 'image/jpeg' }));
                fileInput.files = transfer.files;
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = URL.createObjectURL(blob);
                preview.src = previewUrl;
                preview.hidden = false;
                video.hidden = true;
                submitButton.disabled = false;
                captureButton.disabled = true;
                stopCamera();
                setMessage('Photo captured. Confirm attendance or open the camera to retake it.');
            }, 'image/jpeg', .86);
        });

        window.addEventListener('pagehide', () => {
            stopCamera();
            if (previewUrl) URL.revokeObjectURL(previewUrl);
        });
    });

    document.querySelectorAll('[data-activity-create]').forEach(form => {
        const type = form.querySelector('[data-activity-type]');
        const massReflection = form.querySelector('[data-mass-reflection]');
        const updateTypeOptions = () => {
            if (massReflection) massReflection.hidden = type?.value !== 'Mass';
        };
        type?.addEventListener('change', updateTypeOptions);
        updateTypeOptions();
    });

    document.querySelectorAll('[data-file-preview]').forEach(component => {
        const input = component.querySelector('[data-preview-input]');
        const box = component.querySelector('[data-preview-box]');
        const image = component.querySelector('[data-preview-image]');
        const name = component.querySelector('[data-preview-name]');
        const remove = component.querySelector('[data-preview-remove]');
        let previewUrl;
        const clearPreview = () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = undefined;
            if (input) input.value = '';
            if (image) {
                image.removeAttribute('src');
                image.hidden = true;
            }
            if (name) name.textContent = '';
            if (box) box.hidden = true;
        };
        input?.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return clearPreview();
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = undefined;
            if (name) name.textContent = file.name;
            if (image) {
                if (file.type.startsWith('image/')) {
                    previewUrl = URL.createObjectURL(file);
                    image.src = previewUrl;
                    image.hidden = false;
                } else {
                    image.hidden = true;
                }
            }
            if (box) box.hidden = false;
        });
        remove?.addEventListener('click', clearPreview);
        window.addEventListener('pagehide', () => { if (previewUrl) URL.revokeObjectURL(previewUrl); });
    });

    document.querySelectorAll('[data-mass-camera]').forEach(camera => {
        const video = camera.querySelector('[data-mass-camera-video]');
        const canvas = camera.querySelector('[data-mass-camera-canvas]');
        const preview = camera.querySelector('[data-mass-camera-preview]');
        const fileInput = camera.querySelector('[data-mass-camera-file]');
        const sourceInput = camera.querySelector('[data-mass-camera-source]');
        const startButton = camera.querySelector('[data-mass-camera-start]');
        const captureButton = camera.querySelector('[data-mass-camera-capture]');
        const retakeButton = camera.querySelector('[data-mass-camera-retake]');
        const message = camera.querySelector('[data-mass-camera-message]');
        let stream;
        let previewUrl;

        const stopCamera = () => {
            stream?.getTracks().forEach(track => track.stop());
            stream = undefined;
            if (video) video.srcObject = null;
        };
        const setMessage = value => { if (message) message.textContent = value; };
        const openCamera = async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                setMessage('A live camera is unavailable. Use a current browser over HTTPS or localhost.');
                return;
            }
            try {
                stopCamera();
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                video.srcObject = stream;
                await video.play();
                video.hidden = false;
                preview.hidden = true;
                captureButton.disabled = false;
                retakeButton.hidden = true;
                setMessage('Camera ready. Frame yourself clearly, then select Capture Photo.');
            } catch {
                setMessage('Camera access was not granted. Allow camera access and try again.');
            }
        };
        startButton?.addEventListener('click', openCamera);
        retakeButton?.addEventListener('click', openCamera);
        captureButton?.addEventListener('click', () => {
            if (!stream || !video.videoWidth || !video.videoHeight) return;
            const scale = Math.min(1, 1280 / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(blob => {
                if (!blob) return setMessage('The photo could not be captured. Please try again.');
                const transfer = new DataTransfer();
                transfer.items.add(new File([blob], `mass-attendance-${Date.now()}.jpg`, { type: 'image/jpeg' }));
                fileInput.files = transfer.files;
                sourceInput.value = 'live_camera';
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = URL.createObjectURL(blob);
                preview.src = previewUrl;
                preview.hidden = false;
                video.hidden = true;
                captureButton.disabled = true;
                retakeButton.hidden = false;
                stopCamera();
                setMessage('Photo captured. You may retake it before submitting.');
            }, 'image/jpeg', .86);
        });
        window.addEventListener('pagehide', () => {
            stopCamera();
            if (previewUrl) URL.revokeObjectURL(previewUrl);
        });
    });

    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
        const button = event.submitter ?? form.querySelector('button[type="submit"],button:not([type])');
        if (button) {
            if (button.name) {
                const submittedValue = document.createElement('input');
                submittedValue.type = 'hidden';
                submittedValue.name = button.name;
                submittedValue.value = button.value;
                form.append(submittedValue);
            }
            button.disabled = true;
            button.dataset.label = button.textContent.trim();
            button.textContent = 'Working…';
        }
    }));

    const reveals = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        }), { threshold: .14 });
        reveals.forEach(element => observer.observe(element));
    } else {
        reveals.forEach(element => element.classList.add('is-visible'));
    }
});
