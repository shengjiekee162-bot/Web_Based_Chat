<?php
/**
 * Layout end
 */
?>
        </div>
    </main>
</div>

<!-- Incoming call overlay -->
<div id="incomingCallModal" class="call-overlay d-none" role="dialog" aria-modal="true">
    <div class="call-card">
        <img id="incomingCallAvatar" src="<?= e(asset('images/default-avatar.svg')) ?>" alt="" class="call-avatar">
        <h3 id="incomingCallName">Incoming call</h3>
        <p id="incomingCallType" class="text-muted">Voice call</p>
        <p class="call-hint">The other person must keep WebConnect open in their phone browser (this is not a native phone dialer app).</p>
        <div class="call-actions">
            <button type="button" class="btn btn-success btn-lg rounded-circle" id="btnAcceptCall" title="Accept">
                <i class="fa-solid fa-phone"></i>
            </button>
            <button type="button" class="btn btn-danger btn-lg rounded-circle" id="btnRejectCall" title="Reject">
                <i class="fa-solid fa-phone-slash"></i>
            </button>
        </div>
    </div>
</div>

<!-- Active call overlay -->
<div id="activeCallModal" class="call-overlay d-none" role="dialog" aria-modal="true">
    <div class="call-stage">
        <video id="remoteVideo" autoplay playsinline class="remote-video"></video>
        <video id="localVideo" autoplay playsinline muted class="local-video"></video>
        <div class="voice-call-panel" id="voiceCallPanel">
            <img id="activeCallAvatar" src="<?= e(asset('images/default-avatar.svg')) ?>" alt="" class="call-avatar">
            <h3 id="activeCallName">Calling...</h3>
            <p id="activeCallStatus" class="text-muted">Connecting...</p>
            <button type="button" class="btn btn-primary mt-3 d-none" id="btnGrantMic">
                <i class="fa-solid fa-microphone"></i> Allow microphone &amp; continue
            </button>
            <p class="call-mic-help d-none" id="callMicHelp">
                Look for the browser popup (or the lock icon in the address bar) and choose <strong>Allow</strong>.
            </p>
        </div>
        <div class="call-controls">
            <button type="button" class="btn btn-light rounded-circle" id="btnToggleMute" title="Mute">
                <i class="fa-solid fa-microphone"></i>
            </button>
            <button type="button" class="btn btn-light rounded-circle d-none" id="btnToggleCam" title="Camera">
                <i class="fa-solid fa-video"></i>
            </button>
            <button type="button" class="btn btn-danger rounded-circle" id="btnEndCall" title="End">
                <i class="fa-solid fa-phone-slash"></i>
            </button>
        </div>
    </div>
</div>

<audio id="remoteAudio" autoplay playsinline></audio>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>?v=10"></script>
<script src="<?= e(asset('js/notifications.js')) ?>?v=10"></script>
<script src="<?= e(asset('js/calls.js')) ?>?v=10"></script>
<?php if (!empty($pageScripts)): foreach ((array) $pageScripts as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>?v=10"></script>
<?php endforeach; endif; ?>
</body>
</html>
