
<dialog id="verification-confirmation-modal"
        class="verification-confirmation-modal"
        aria-labelledby="verification-modal-title">

    <form method="POST"
          id="verification-decision-form">

        @csrf

        <input type="hidden"
               name="decision"
               id="verification-decision">

        <div id="verification-modal-icon"
             class="verification-modal-icon">

            <i data-lucide="check-circle"></i>

        </div>

        <h2 id="verification-modal-title">

            Confirm Decision

        </h2>

        <p id="verification-modal-description">

            Are you sure you want to continue?

        </p>

        <div id="verification-reason-field"
             hidden>

            <label for="verification-reason">

                Reason for rejection

            </label>

            <textarea id="verification-reason"
                      name="reason"
                      rows="4"
                      maxlength="1000"
                      placeholder="Enter reason for rejection">
            </textarea>

        </div>

        <div class="verification-modal-actions">

            <button type="button"
                    id="verification-modal-cancel"
                    class="verification-button outline">

                Cancel

            </button>

            <button type="submit"
                    id="verification-modal-confirm"
                    class="verification-button primary">

                Confirm

            </button>

        </div>

    </form>

</dialog>

<script>

document.addEventListener('DOMContentLoaded', () => {

    const modal = document.getElementById(
        'verification-confirmation-modal'
    );

    const form = document.getElementById(
        'verification-decision-form'
    );

    const decisionInput = document.getElementById(
        'verification-decision'
    );

    const title = document.getElementById(
        'verification-modal-title'
    );

    const description = document.getElementById(
        'verification-modal-description'
    );

    const reasonField = document.getElementById(
        'verification-reason-field'
    );

    const reason = document.getElementById(
        'verification-reason'
    );

    const confirmButton = document.getElementById(
        'verification-modal-confirm'
    );

    const icon = document.getElementById(
        'verification-modal-icon'
    );

    document.querySelectorAll(
        '[data-review-url]'
    ).forEach(button => {

        button.addEventListener('click', () => {

            if (button.disabled) return;

            const decision = button.dataset.decision;

            const label = button.dataset.reviewLabel;

            const rejected = decision === 'reject';

            form.action = button.dataset.reviewUrl;

            decisionInput.value = decision;

            title.textContent = rejected

                ? 'Confirm Rejection'

                : 'Confirm Approval';

            description.textContent =

                `Are you sure you want to ${decision} ${label}?`;

            reason.value = '';

            reasonField.hidden = !rejected;

            reason.required = rejected;

            reason.disabled = !rejected;

            confirmButton.disabled = false;

            confirmButton.textContent = rejected

                ? 'Reject'

                : 'Approve';

            confirmButton.className =

                'verification-button '

                + (rejected ? 'danger' : 'primary');

            icon.className =

                'verification-modal-icon '

                + (rejected ? 'rejected' : 'approved');

            icon.replaceChildren();

            const symbol = document.createElement('i');

            symbol.setAttribute(

                'data-lucide',

                rejected ? 'x-circle' : 'check-circle'

            );

            icon.appendChild(symbol);

            document.querySelectorAll(
                '.verification-action-menu[open]'
            ).forEach(menu => menu.removeAttribute('open'));

            window.lucide?.createIcons();

            modal.showModal();

        });

    });

    document.getElementById(
        'verification-modal-cancel'
    ).addEventListener('click', () => {

        modal.close();

    });

    form.addEventListener('submit', () => {

        confirmButton.disabled = true;

        confirmButton.textContent = 'Processing...';

    });

    window.lucide?.createIcons();

});

</script>
