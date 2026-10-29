import * as THREE from 'three';

/**
 * VITA Robot — procedural 3D character for Three.js.
 * Usage: const avatar = createVitaRobot(document.querySelector('#vita-robot'));
 * avatar.setState('talking'); avatar.dispose();
 */
export function createVitaRobot(container) {
  if (!container) throw new Error('VITA Robot: container is required');
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(36, 1, 0.1, 100);
  camera.position.set(0, 0.15, 6.9);
  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.45;
  renderer.setClearColor(0x000000, 0);
  container.appendChild(renderer.domElement);
  renderer.domElement.setAttribute('aria-hidden', 'true');

  scene.add(new THREE.HemisphereLight(0xffffff, 0x6a70b6, 2.2));
  const key = new THREE.DirectionalLight(0xffffff, 3.0);
  key.position.set(-3, 5, 6);
  scene.add(key);
  const rim = new THREE.PointLight(0x8d58ff, 30, 12);
  rim.position.set(3, 2, -2);
  scene.add(rim);

  const white = new THREE.MeshPhysicalMaterial({ color: 0xe9f5ff, metalness: 0.12, roughness: 0.25, clearcoat: 0.8 });
  const dark = new THREE.MeshPhysicalMaterial({ color: 0x101a31, metalness: 0.18, roughness: 0.15, clearcoat: 1 });
  const joint = new THREE.MeshStandardMaterial({ color: 0x34415f, metalness: 0.55, roughness: 0.3 });
  const cyan = new THREE.MeshStandardMaterial({ color: 0x38dfff, emissive: 0x00c6ff, emissiveIntensity: 2.2, toneMapped: false });
  const purple = new THREE.MeshStandardMaterial({ color: 0xa279ff, emissive: 0x7b2ff7, emissiveIntensity: 0.7 });
  const materials = [white, dark, joint, cyan, purple];

  function sphere(parent, material, scale, position) {
    const mesh = new THREE.Mesh(new THREE.SphereGeometry(1, 32, 24), material);
    mesh.scale.set(...scale);
    mesh.position.set(...position);
    parent.add(mesh);
    return mesh;
  }
  function cylinder(parent, material, radiusTop, radiusBottom, height, position) {
    const mesh = new THREE.Mesh(new THREE.CylinderGeometry(radiusTop, radiusBottom, height, 32), material);
    mesh.position.set(...position);
    parent.add(mesh);
    return mesh;
  }
  function ring(parent, material, radius, tube, position, rotation = [0, 0, 0]) {
    const mesh = new THREE.Mesh(new THREE.TorusGeometry(radius, tube, 12, 48), material);
    mesh.position.set(...position);
    mesh.rotation.set(...rotation);
    parent.add(mesh);
    return mesh;
  }

  const root = new THREE.Group();
  scene.add(root);
  const bodyGroup = new THREE.Group();
  root.add(bodyGroup);
  sphere(bodyGroup, white, [0.73, 0.7, 0.59], [0, -0.72, 0]);
  sphere(bodyGroup, dark, [0.53, 0.16, 0.46], [0, -1.33, 0]);
  cylinder(bodyGroup, cyan, 0.34, 0.34, 0.08, [0, -1.4, 0]);
  const thrusterGlow = new THREE.PointLight(0x00c6ff, 3.5, 3);
  thrusterGlow.position.set(0, -1.5, 0);
  bodyGroup.add(thrusterGlow);
  // Chest badge: heart-like pair of lobes, with white medical cross.
  const badge = new THREE.Group();
  badge.position.set(0, -0.63, 0.56);
  bodyGroup.add(badge);
  sphere(badge, cyan, [0.16, 0.17, 0.045], [-0.11, 0.055, 0]);
  sphere(badge, purple, [0.16, 0.17, 0.045], [0.11, 0.055, 0]);
  const point = new THREE.Mesh(new THREE.ConeGeometry(0.235, 0.31, 3), purple);
  point.rotation.z = Math.PI;
  point.position.set(0, -0.15, 0);
  badge.add(point);
  const crossV = new THREE.Mesh(new THREE.BoxGeometry(0.065, 0.29, 0.035), white);
  crossV.position.z = 0.08;
  badge.add(crossV);
  const crossH = new THREE.Mesh(new THREE.BoxGeometry(0.23, 0.065, 0.035), white);
  crossH.position.z = 0.08;
  badge.add(crossH);

  const neck = cylinder(root, joint, 0.23, 0.23, 0.2, [0, -0.03, 0]);
  const headPivot = new THREE.Group();
  headPivot.position.y = 0.26;
  root.add(headPivot);
  sphere(headPivot, white, [1.13, 0.92, 0.76], [0, 0.47, 0]);
  // Face screen: slightly inset front oval; cyan eyes and mouth on top.
  sphere(headPivot, dark, [0.96, 0.7, 0.16], [0, 0.46, 0.685]);
  function makeEye(x) {
    const eye = new THREE.Group();
    eye.position.set(x, 0.58, 0.824);
    headPivot.add(eye);
    sphere(eye, dark, [0.125, 0.15, 0.025], [0, 0, 0.012]);
    ring(eye, cyan, 0.115, 0.018, [0, 0, 0.039]);
    sphere(eye, cyan, [0.066, 0.083, 0.018], [0, 0, 0.043]);
    sphere(eye, dark, [0.031, 0.045, 0.012], [0, -0.003, 0.059]);
    sphere(eye, white, [0.025, 0.03, 0.009], [-0.026, 0.036, 0.069]);
    sphere(eye, white, [0.012, 0.014, 0.006], [0.033, -0.034, 0.07]);
    return eye;
  }
  const leftEye = makeEye(-0.37);
  const rightEye = makeEye(0.37);
  const mouth = sphere(headPivot, cyan, [0.18, 0.045, 0.035], [0, 0.22, 0.842]);
  const eyeBaseHeight = leftEye.scale.y;
  const mouthBaseHeight = mouth.scale.y;
  for (const side of [-1, 1]) {
    sphere(headPivot, purple, [0.21, 0.37, 0.34], [side * 1.06, 0.46, 0]);
    sphere(headPivot, cyan, [0.14, 0.25, 0.25], [side * 1.18, 0.46, 0.03]);
    const fin = sphere(headPivot, purple, [0.13, 0.43, 0.11], [side * 0.88, 1.05, -0.08]);
    fin.rotation.z = side * -0.28;
    const finLight = sphere(headPivot, cyan, [0.055, 0.34, 0.045], [side * 0.88, 1.07, 0.005]);
    finLight.rotation.z = side * -0.28;
  }
  const helmetAccent = sphere(headPivot, cyan, [0.62, 0.15, 0.34], [0, 1.16, 0.30]);
  helmetAccent.rotation.z = -0.04;

  function makeArm(side) {
    const pivot = new THREE.Group();
    pivot.position.set(side * 0.72, -0.43, 0);
    bodyGroup.add(pivot);
    sphere(pivot, joint, [0.22, 0.23, 0.22], [side * 0.08, 0, 0]);
    sphere(pivot, white, [0.23, 0.36, 0.24], [side * 0.33, -0.26, 0]);
    sphere(pivot, joint, [0.18, 0.18, 0.18], [side * 0.49, -0.51, 0]);
    const hand = sphere(pivot, white, [0.23, 0.24, 0.19], [side * 0.53, -0.68, 0.03]);
    for (let i = 0; i < 3; i++) {
      sphere(pivot, joint, [0.07, 0.14, 0.075], [side * (0.39 + i * 0.12), -0.88, 0.1]);
    }
    return pivot;
  }
  const leftArm = makeArm(-1);
  const rightArm = makeArm(1);

  let state = 'idle';
  let disposed = false;
  let visible = true;
  let frame = 0;
  let last = performance.now();
  let elapsed = 0;
  const validStates = new Set(['idle', 'listening', 'thinking', 'talking', 'happy', 'greeting']);
  const clock = new THREE.Clock();
  function setState(next) {
    if (!validStates.has(next)) throw new Error(`Unknown robot state: ${next}`);
    state = next;
  }
  function lerp(a, b, speed, dt) { return THREE.MathUtils.lerp(a, b, 1 - Math.exp(-speed * dt)); }
  function renderFrame() {
    if (disposed) return;
    frame = requestAnimationFrame(renderFrame);
    if (!visible) return;
    const dt = Math.min(clock.getDelta(), 0.05);
    elapsed += dt;
    const t = elapsed;
    root.position.y = Math.sin(t * 1.7) * 0.095;
    root.rotation.y = Math.sin(t * 0.5) * 0.1;
    const greeting = state === 'greeting';
    const talking = state === 'talking';
    const thinking = state === 'thinking';
    const happy = state === 'happy';
    const listening = state === 'listening';
    headPivot.rotation.z = lerp(headPivot.rotation.z, listening ? -0.16 : thinking ? 0.16 : Math.sin(t * 0.7) * 0.035, 5, dt);
    headPivot.rotation.x = lerp(headPivot.rotation.x, thinking ? -0.16 : 0, 5, dt);
    leftArm.rotation.z = lerp(leftArm.rotation.z, happy ? -1.2 : talking ? -0.22 + Math.sin(t * 8) * 0.24 : -0.06, 6, dt);
    rightArm.rotation.z = lerp(rightArm.rotation.z, greeting ? -2.5 + Math.sin(t * 9) * 0.24 : happy ? 1.2 : talking ? 0.22 + Math.sin(t * 7) * 0.24 : thinking ? -0.6 : 0.06, 6, dt);
    rightArm.rotation.x = lerp(rightArm.rotation.x, greeting ? -0.35 : 0, 5, dt);
    const mouthOpen = talking ? 0.9 + Math.abs(Math.sin(t * 12)) * 1.7 : happy ? 1.8 : 1;
    mouth.scale.y = lerp(mouth.scale.y, mouthBaseHeight * mouthOpen, 12, dt);
    const blink = (t % 4.4) < 0.13 ? 0.08 : 1;
    leftEye.scale.y = lerp(leftEye.scale.y, eyeBaseHeight * blink, 25, dt);
    rightEye.scale.y = lerp(rightEye.scale.y, eyeBaseHeight * blink, 25, dt);
    thrusterGlow.intensity = 3.2 + Math.sin(t * 4) * 0.5;
    renderer.render(scene, camera);
    container.classList.add('has-3d-robot');
  }

  const observer = new ResizeObserver(() => {
    const width = container.clientWidth;
    const height = container.clientHeight;
    if (width <= 0 || height <= 0) return;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height, false);
  });
  observer.observe(container);
  const visibilityObserver = new IntersectionObserver(entries => { visible = entries[0]?.isIntersecting ?? true; });
  visibilityObserver.observe(container);
  // Initial render size, including when resize observer is delayed.
  const width = Math.max(container.clientWidth, 1);
  const height = Math.max(container.clientHeight, 1);
  camera.aspect = width / height;
  camera.updateProjectionMatrix();
  renderer.setSize(width, height, false);
  renderFrame();

  function dispose() {
    if (disposed) return;
    disposed = true;
    cancelAnimationFrame(frame);
    observer.disconnect();
    visibilityObserver.disconnect();
    const geometries = new Set();
    scene.traverse(obj => { if (obj.isMesh && obj.geometry) geometries.add(obj.geometry); });
    geometries.forEach(geometry => geometry.dispose());
    materials.forEach(material => material.dispose());
    renderer.dispose();
    renderer.domElement.remove();
  }
  return { setState, get state() { return state; }, dispose, scene, robot: root };
}
