(() => {
  const { api, toast, notifyDesktop } = window.WC;
  const APP = window.APP;

  let pc = null;
  let localStream = null;
  let remoteStream = null;
  let callId = null;
  let callType = 'voice';
  let isCaller = false;
  let signalAfter = 0;
  let pollTimer = null;
  let muted = false;
  let camOff = false;
  let ringTimer = null;
  let ringSoundTimer = null;
  let pendingIce = [];
  let ending = false;
  const RING_TIMEOUT_MS = Number(APP?.callRingTimeoutMs) || 45000;
  const MEDIA_TIMEOUT_MS = 15000;

  const incomingModal = document.getElementById('incomingCallModal');
  const activeModal = document.getElementById('activeCallModal');
  const remoteVideo = document.getElementById('remoteVideo');
  const localVideo = document.getElementById('localVideo');
  const remoteAudio = document.getElementById('remoteAudio');
  const stage = activeModal?.querySelector('.call-stage');

  function rtcConfig() {
    return {
      iceServers: APP.iceServers || [{ urls: 'stun:stun.l.google.com:19302' }],
      iceCandidatePoolSize: 4,
    };
  }

  function withTimeout(promise, ms, message) {
    let timer;
    const timeout = new Promise((_, reject) => {
      timer = setTimeout(() => reject(new Error(message)), ms);
    });
    return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
  }

  function openOverlay(el) {
    if (!el) return;
    el.classList.remove('d-none');
    el.classList.add('is-open');
  }

  function closeOverlay(el) {
    if (!el) return;
    el.classList.add('d-none');
    el.classList.remove('is-open');
  }

  function setStatus(text) {
    const el = document.getElementById('activeCallStatus');
    if (el) el.textContent = text;
  }

  function stopLocalStream() {
    if (localStream) {
      localStream.getTracks().forEach((t) => {
        try { t.stop(); } catch {}
      });
      localStream = null;
    }
    if (localVideo) localVideo.srcObject = null;
  }

  function stopRingAlert() {
    clearInterval(ringSoundTimer);
    ringSoundTimer = null;
  }

  function startRingAlert(name) {
    stopRingAlert();
    try { navigator.vibrate?.([300, 120, 300, 120, 300]); } catch {}
    try { notifyDesktop?.('Incoming call', `${name || 'Someone'} is calling you`, 'chat.php'); } catch {}
    toast(`${name || 'Someone'} is calling you…`, 'info');
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      const ctx = new Ctx();
      const beep = () => {
        if (!incomingModal?.classList.contains('is-open')) return;
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.frequency.value = 880;
        g.gain.value = 0.06;
        o.connect(g);
        g.connect(ctx.destination);
        o.start();
        o.stop(ctx.currentTime + 0.18);
      };
      beep();
      ringSoundTimer = setInterval(() => {
        try { navigator.vibrate?.(200); } catch {}
        beep();
      }, 2200);
    } catch {}
  }

  function mediaErrorMessage(err, wantedType) {
    const name = err?.name || '';
    const msg = (err?.message || '').toLowerCase();
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
      return 'Microphone/camera permission denied. Tap Allow when the browser asks.';
    }
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
      return 'No microphone or camera was found on this device.';
    }
    if (name === 'NotReadableError' || name === 'TrackStartError' || msg.includes('device in use') || msg.includes('in use')) {
      return wantedType === 'video'
        ? 'Camera/microphone is in use. Close other tabs/apps, or use voice call.'
        : 'Microphone is in use by another app or tab. Close it and try again.';
    }
    if (name === 'SecurityError') {
      return 'Media access blocked. Use HTTPS in a normal browser window.';
    }
    if (msg.includes('timed out') || msg.includes('timeout')) {
      return 'Microphone permission timed out. Allow mic in the site settings, then try again.';
    }
    return err?.message || 'Unable to access microphone/camera.';
  }

  function setMicPrompt(show) {
    document.getElementById('btnGrantMic')?.classList.toggle('d-none', !show);
    document.getElementById('callMicHelp')?.classList.toggle('d-none', !show);
  }

  function applyLocalPreview() {
    if (!localVideo) return;
    localVideo.srcObject = callType === 'video' ? localStream : null;
    localVideo.muted = true;
    localVideo.playsInline = true;
    try { localVideo.play(); } catch {}
  }

  function waitForGrantClick(type) {
    return new Promise((resolve) => {
      const btn = document.getElementById('btnGrantMic');
      if (!btn) return resolve(false);
      setStatus('Tap the blue button to allow microphone');
      setMicPrompt(true);
      let done = false;
      const finish = (ok) => {
        if (done) return;
        done = true;
        btn.removeEventListener('click', onClick);
        clearTimeout(timer);
        resolve(ok);
      };
      const onClick = async () => {
        try {
          const wantVideo = type === 'video' && callType === 'video';
          localStream = await navigator.mediaDevices.getUserMedia(
            wantVideo
              ? { audio: true, video: { facingMode: 'user' } }
              : { audio: true, video: false }
          );
          if (wantVideo && !localStream.getVideoTracks().length) {
            callType = 'voice';
            stage?.classList.remove('video-mode');
            stage?.classList.add('voice-mode');
          }
          applyLocalPreview();
          setMicPrompt(false);
          finish(true);
        } catch {
          finish(false);
        }
      };
      btn.addEventListener('click', onClick);
      const timer = setTimeout(() => finish(!!localStream), MEDIA_TIMEOUT_MS);
    });
  }

  async function ensureMedia(type, preStream = null) {
    if (!window.isSecureContext) {
      throw new Error('Calls require HTTPS. Open the site via https://…');
    }
    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error('This browser does not support camera/microphone access.');
    }

    if (preStream) {
      stopLocalStream();
      localStream = preStream;
      if (type === 'video' && !localStream.getVideoTracks().length) {
        callType = 'voice';
        stage?.classList.remove('video-mode');
        stage?.classList.add('voice-mode');
        document.getElementById('btnToggleCam')?.classList.add('d-none');
      }
      applyLocalPreview();
      setMicPrompt(false);
      return localStream;
    }

    stopLocalStream();
    setStatus('Allow microphone' + (type === 'video' ? '/camera' : '') + '…');
    setMicPrompt(true);

    try {
      const perm = await navigator.permissions?.query?.({ name: 'microphone' });
      if (perm?.state === 'denied') {
        throw new Error('Microphone is blocked. Click the lock icon in the address bar → Permissions → Microphone → Allow, then reload.');
      }
    } catch (e) {
      if (e.message && e.message.includes('blocked')) throw e;
    }

    const tryGet = (constraints) => withTimeout(
      navigator.mediaDevices.getUserMedia(constraints),
      MEDIA_TIMEOUT_MS,
      'Microphone permission timed out'
    );

    try {
      if (type === 'video') {
        try {
          localStream = await tryGet({
            audio: { echoCancellation: true, noiseSuppression: true },
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
          });
        } catch (videoErr) {
          console.warn('Video getUserMedia failed, falling back to audio:', videoErr);
          toast(mediaErrorMessage(videoErr, 'video') + ' Falling back to voice-only.', 'error');
          localStream = await tryGet({
            audio: { echoCancellation: true, noiseSuppression: true },
            video: false,
          });
          callType = 'voice';
          stage?.classList.remove('video-mode');
          stage?.classList.add('voice-mode');
          document.getElementById('btnToggleCam')?.classList.add('d-none');
        }
      } else {
        localStream = await tryGet({
          audio: { echoCancellation: true, noiseSuppression: true },
          video: false,
        });
      }
    } catch (err) {
      const ok = await waitForGrantClick(type);
      if (!ok) {
        const e = new Error(mediaErrorMessage(err, type));
        e.cause = err;
        throw e;
      }
    }

    applyLocalPreview();
    setMicPrompt(false);
    return localStream;
  }

  function postIce(candidate) {
    if (!callId || !candidate) return;
    api('api/calls.php?action=signal', {
      method: 'POST',
      body: { call_id: callId, signal_type: 'ice', payload: candidate },
    }).catch(() => {});
  }

  function flushIce() {
    const list = pendingIce.splice(0, pendingIce.length);
    list.forEach(postIce);
  }

  async function playRemoteMedia() {
    if (remoteVideo && remoteStream) {
      remoteVideo.srcObject = remoteStream;
      remoteVideo.playsInline = true;
      remoteVideo.autoplay = true;
      try { await remoteVideo.play(); } catch (e) { console.warn('remoteVideo.play', e); }
    }
    if (remoteAudio && remoteStream) {
      remoteAudio.srcObject = remoteStream;
      remoteAudio.muted = false;
      remoteAudio.autoplay = true;
      try { await remoteAudio.play(); } catch (e) { console.warn('remoteAudio.play', e); }
    }
  }

  function endCallUi(reason) {
    if (ending) return;
    ending = true;
    clearInterval(pollTimer);
    clearTimeout(ringTimer);
    pollTimer = null;
    if (reason) {
      setStatus(reason);
      toast(reason, 'info');
    }
    setTimeout(() => cleanup(), reason ? 900 : 0);
  }

  function cleanup() {
    ending = false;
    clearInterval(pollTimer);
    clearTimeout(ringTimer);
    stopRingAlert();
    pollTimer = null;
    ringTimer = null;
    pendingIce = [];
    if (pc) {
      try { pc.close(); } catch {}
      pc = null;
    }
    stopLocalStream();
    remoteStream = null;
    if (remoteVideo) remoteVideo.srcObject = null;
    if (remoteAudio) remoteAudio.srcObject = null;
    callId = null;
    muted = false;
    camOff = false;
    setMicPrompt(false);
    closeOverlay(incomingModal);
    closeOverlay(activeModal);
  }

  function createPeer() {
    if (pc) {
      try { pc.close(); } catch {}
    }
    pc = new RTCPeerConnection(rtcConfig());
    pendingIce = [];
    remoteStream = new MediaStream();

    pc.onicecandidate = (ev) => {
      if (!ev.candidate) return;
      if (!callId) pendingIce.push(ev.candidate.toJSON ? ev.candidate.toJSON() : ev.candidate);
      else postIce(ev.candidate.toJSON ? ev.candidate.toJSON() : ev.candidate);
    };

    pc.ontrack = (ev) => {
      // Accumulate tracks so audio+video both play
      ev.streams[0]?.getTracks().forEach((t) => {
        if (!remoteStream.getTracks().some((x) => x.id === t.id)) {
          remoteStream.addTrack(t);
        }
      });
      if (!ev.streams[0] && ev.track) {
        if (!remoteStream.getTracks().some((x) => x.id === ev.track.id)) {
          remoteStream.addTrack(ev.track);
        }
      }
      playRemoteMedia();
    };

    pc.onconnectionstatechange = () => {
      const state = pc?.connectionState;
      if (state === 'connected') {
        setStatus('Connected');
        playRemoteMedia();
      } else if (state === 'connecting') {
        setStatus('Connecting…');
      } else if (state === 'failed') {
        setStatus('Connection failed');
        toast('Call connection failed. Try the same Wi‑Fi or wait a moment and retry.', 'error');
      } else if (state === 'disconnected') {
        setStatus('Reconnecting…');
      } else if (state === 'closed') {
        setStatus('Call ended');
      }
    };
  }

  function showActive(peer, type) {
    if (!activeModal) {
      toast('Call UI missing on this page. Re-upload layout files.', 'error');
      return;
    }
    openOverlay(activeModal);
    stage?.classList.toggle('video-mode', type === 'video');
    stage?.classList.toggle('voice-mode', type !== 'video');
    document.getElementById('btnToggleCam')?.classList.toggle('d-none', type !== 'video');
    const nameEl = document.getElementById('activeCallName');
    if (nameEl) nameEl.textContent = peer?.username || 'Call';
    const av = document.getElementById('activeCallAvatar');
    if (av && peer?.avatar_url) av.src = peer.avatar_url;
    setStatus(isCaller ? 'Calling…' : 'Connecting…');
  }

  async function startCall(userId, type = 'voice', peer = null, preStream = null) {
    userId = Number(userId);
    if (!userId) {
      toast('Open a chat with a friend before calling.', 'error');
      return;
    }
    if (userId === Number(APP.userId)) {
      toast('You cannot call yourself.', 'error');
      return;
    }
    if (callId || activeModal?.classList.contains('is-open')) {
      toast('You are already in a call.', 'error');
      return;
    }
    try {
      ending = false;
      callType = type === 'video' ? 'video' : 'voice';
      isCaller = true;
      showActive(peer, callType);

      // Media BEFORE offer so SDP includes real tracks (fixes silent / blank video)
      await ensureMedia(callType, preStream);
      createPeer();
      localStream.getTracks().forEach((t) => pc.addTrack(t, localStream));

      const offer = await pc.createOffer({
        offerToReceiveAudio: true,
        offerToReceiveVideo: callType === 'video',
      });
      await pc.setLocalDescription(offer);

      setStatus('Ringing…');
      const res = await api('api/calls.php?action=start', {
        method: 'POST',
        body: { callee_id: userId, call_type: callType, offer_sdp: offer.sdp },
      });
      callId = res.data.call_id;
      flushIce();
      signalAfter = 0;
      setStatus('Calling… waiting for answer');
      startPolling();

      clearTimeout(ringTimer);
      ringTimer = setTimeout(async () => {
        if (!callId || !isCaller) return;
        setStatus('No answer');
        await api('api/calls.php?action=cancel', { method: 'POST', body: { call_id: callId } }).catch(() => {});
        toast('Call timed out — no answer.', 'error');
        cleanup();
      }, RING_TIMEOUT_MS);
    } catch (err) {
      console.error('startCall failed', err);
      setStatus(err.message || 'Unable to start call.');
      toast(err.message || 'Unable to start call.', 'error');
      setTimeout(() => cleanup(), 2200);
    }
  }

  function startPolling() {
    clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
      if (!callId) return;
      const id = callId;
      try {
        const res = await api(`api/calls.php?action=poll_signals&call_id=${id}&after_id=${signalAfter}`);
        const status = res.data?.status;
        if (['rejected', 'cancelled', 'ended', 'missed'].includes(status)) {
          endCallUi(status === 'rejected' ? 'Call declined' : 'Call ended');
          return;
        }
        if (isCaller && res.data.answer_sdp && pc && !pc.currentRemoteDescription) {
          clearTimeout(ringTimer);
          await pc.setRemoteDescription({ type: 'answer', sdp: res.data.answer_sdp });
          setStatus('Connecting…');
          playRemoteMedia();
        }
        for (const sig of res.data.signals || []) {
          signalAfter = Math.max(signalAfter, sig.id);
          if (sig.signal_type === 'ice' && sig.payload) {
            try {
              await pc.addIceCandidate(sig.payload);
            } catch (e) {
              console.warn('addIceCandidate', e);
            }
          } else if (sig.signal_type === 'hangup') {
            endCallUi('The other person hung up');
            return;
          }
        }
      } catch (err) {
        console.warn('call poll failed', err);
      }
    }, 600);
  }

  async function pollIncoming() {
    try {
      const res = await api('api/calls.php?action=incoming');
      if (!res.data) {
        // Caller cancelled while we were still on incoming UI
        if (!callId && incomingModal?.classList.contains('is-open')) {
          stopRingAlert();
          closeOverlay(incomingModal);
          toast('Caller hung up', 'info');
        }
        return;
      }
      if (callId) return;
      if (incomingModal?.classList.contains('is-open') && Number(incomingModal.dataset.callId) === Number(res.data.id)) {
        return;
      }
      const call = res.data;
      openOverlay(incomingModal);
      document.getElementById('incomingCallName').textContent = call.caller.username;
      document.getElementById('incomingCallType').textContent = call.call_type === 'video' ? 'Video call' : 'Voice call';
      document.getElementById('incomingCallAvatar').src = call.caller.avatar_url;
      incomingModal.dataset.callId = call.id;
      incomingModal.dataset.offer = call.offer_sdp;
      incomingModal.dataset.type = call.call_type;
      incomingModal._caller = call.caller;
      startRingAlert(call.caller.username);
    } catch {}
  }

  document.getElementById('btnAcceptCall')?.addEventListener('click', async () => {
    try {
      stopRingAlert();
      callId = Number(incomingModal.dataset.callId);
      callType = incomingModal.dataset.type || 'voice';
      isCaller = false;
      const offerSdp = incomingModal.dataset.offer;
      closeOverlay(incomingModal);
      showActive(incomingModal._caller, callType);

      await ensureMedia(callType);
      createPeer();
      localStream.getTracks().forEach((t) => pc.addTrack(t, localStream));
      await pc.setRemoteDescription({ type: 'offer', sdp: offerSdp });
      // Apply any ICE that arrives after callId is already set
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      await api('api/calls.php?action=accept', {
        method: 'POST',
        body: { call_id: callId, answer_sdp: answer.sdp },
      });
      flushIce();
      setStatus('Connecting…');
      signalAfter = 0;
      startPolling();
      playRemoteMedia();
    } catch (err) {
      const id = callId;
      console.error('acceptCall failed', err);
      toast(err.message || 'Unable to accept call.', 'error');
      cleanup();
      if (id) {
        api('api/calls.php?action=reject', { method: 'POST', body: { call_id: id } }).catch(() => {});
      }
    }
  });

  document.getElementById('btnRejectCall')?.addEventListener('click', async () => {
    stopRingAlert();
    const id = Number(incomingModal?.dataset.callId);
    if (id) await api('api/calls.php?action=reject', { method: 'POST', body: { call_id: id } }).catch(() => {});
    closeOverlay(incomingModal);
  });

  document.getElementById('btnEndCall')?.addEventListener('click', async () => {
    const id = callId;
    const action = isCaller && pc && !pc.currentRemoteDescription ? 'cancel' : 'end';
    // Keep polling alive until server gets hangup, then close local UI
    if (id) {
      try {
        await api(`api/calls.php?action=${action}`, { method: 'POST', body: { call_id: id } });
      } catch {}
    }
    endCallUi('Call ended');
  });

  document.getElementById('btnToggleMute')?.addEventListener('click', () => {
    muted = !muted;
    localStream?.getAudioTracks().forEach((t) => { t.enabled = !muted; });
    const btn = document.getElementById('btnToggleMute');
    if (btn) {
      btn.innerHTML = muted
        ? '<i class="fa-solid fa-microphone-slash"></i>'
        : '<i class="fa-solid fa-microphone"></i>';
    }
  });

  document.getElementById('btnToggleCam')?.addEventListener('click', () => {
    camOff = !camOff;
    localStream?.getVideoTracks().forEach((t) => { t.enabled = !camOff; });
    const btn = document.getElementById('btnToggleCam');
    if (btn) {
      btn.innerHTML = camOff
        ? '<i class="fa-solid fa-video-slash"></i>'
        : '<i class="fa-solid fa-video"></i>';
    }
  });

  if (activeModal && incomingModal) {
    setInterval(pollIncoming, APP.poll?.calls || 800);
    pollIncoming();
  } else {
    console.warn('Call overlays missing — voice/video UI will not show.');
  }

  window.WCCalls = { startCall, cleanup };
})();
