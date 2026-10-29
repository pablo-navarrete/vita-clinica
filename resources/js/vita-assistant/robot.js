import * as THREE from 'three';
import { animationPose } from './animations.js';

const white = new THREE.MeshStandardMaterial({ color: 0xf7fbff, roughness: 0.32, metalness: 0.16 });
const cyan = new THREE.MeshStandardMaterial({ color: 0x00c6ff, emissive: 0x00aaff, emissiveIntensity: 0.8, roughness: 0.25 });
const purple = new THREE.MeshStandardMaterial({ color: 0x7b2ff7, emissive: 0x351080, emissiveIntensity: 0.28, roughness: 0.35 });
const dark = new THREE.MeshStandardMaterial({ color: 0x10213b, roughness: 0.24, metalness: 0.25 });
const blush = new THREE.MeshStandardMaterial({ color: 0xffa4cc, emissive: 0x7d174b, emissiveIntensity: 0.16, roughness: 0.48 });

function mesh(geometry, material, parent, position, scale) {
    const item = new THREE.Mesh(geometry, material);
    item.position.set(...position);
    if (scale) item.scale.set(...scale);
    item.castShadow = true;
    item.receiveShadow = true;
    parent.add(item);
    return item;
}

function makeArm(scene, side) {
    const pivot = new THREE.Group();
    pivot.position.set(side * 0.48, -0.49, 0);
    scene.add(pivot);
    const upper = mesh(new THREE.CapsuleGeometry(0.105, 0.27, 5, 10), white, pivot, [side * 0.15, -0.16, 0], [1, 1, 1]);
    upper.rotation.z = side * -0.52;
    mesh(new THREE.SphereGeometry(0.13, 20, 16), purple, pivot, [side * 0.29, -0.31, 0]);
    const forearm = new THREE.Group();
    forearm.position.set(side * 0.29, -0.31, 0);
    pivot.add(forearm);
    const segment = mesh(new THREE.CapsuleGeometry(0.085, 0.2, 4, 9), white, forearm, [side * 0.035, -0.16, 0]);
    segment.rotation.z = side * -0.12;
    mesh(new THREE.SphereGeometry(0.12, 18, 14), cyan, forearm, [side * 0.065, -0.31, 0.015]);
    return { pivot, forearm };
}

export class VitaRobot {
    constructor(canvas) {
        this.canvas = canvas;
        this.state = 'idle';
        this.pose = {};
        this.currentPose = {};
        this.clock = new THREE.Clock();
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(34, 1, 0.1, 20);
        this.camera.position.set(0, 0.16, 5.4);
        this.renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'low-power' });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.6));
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.setClearColor(0x000000, 0);

        this.scene.add(new THREE.HemisphereLight(0xe9f8ff, 0x7988b7, 2.1));
        const key = new THREE.DirectionalLight(0xffffff, 2.3);
        key.position.set(-3, 4, 5);
        this.scene.add(key);
        const rim = new THREE.PointLight(0x00c6ff, 2.8, 8);
        rim.position.set(2, 1, 2.5);
        this.scene.add(rim);

        this.root = new THREE.Group();
        this.scene.add(this.root);
        const torso = mesh(new THREE.SphereGeometry(0.54, 32, 24), white, this.root, [0, -0.63, 0], [0.76, 0.72, 0.58]);
        torso.rotation.x = -0.05;
        mesh(new THREE.SphereGeometry(0.16, 22, 18), purple, this.root, [0, -0.2, 0]);
        mesh(new THREE.SphereGeometry(0.84, 36, 28), white, this.root, [0, 0.59, 0], [1, 0.9, 0.78]);

        this.head = this.root.children[this.root.children.length - 1];
        mesh(new THREE.SphereGeometry(0.68, 32, 24), dark, this.head, [0, -0.02, 0.51], [1, 0.72, 0.25]);
        this.eyeIrisMeshes = [];
        this.eyes = [-1, 1].map(side => {
            const eye = new THREE.Group();
            eye.position.set(side * 0.22, 0.075, 0.68);
            eye.userData.baseY = eye.position.y;
            this.head.add(eye);
            mesh(new THREE.SphereGeometry(0.112, 24, 20), white, eye, [0, 0, 0], [1, 1, 0.64]);
            const iris = mesh(new THREE.SphereGeometry(0.073, 24, 20), cyan, eye, [0, -0.006, 0.061], [1, 1, 0.62]);
            this.eyeIrisMeshes.push(iris);
            mesh(new THREE.SphereGeometry(0.036, 20, 16), dark, eye, [0, -0.012, 0.102], [1, 1, 0.62]);
            mesh(new THREE.SphereGeometry(0.025, 16, 12), white, eye, [-0.025, 0.035, 0.116], [1, 1, 0.6]);
            mesh(new THREE.SphereGeometry(0.012, 12, 10), white, eye, [0.03, -0.035, 0.119], [1, 1, 0.55]);
            return eye;
        });
        [-1, 1].forEach(side => mesh(new THREE.SphereGeometry(0.075, 20, 16), blush, this.head, [side * 0.42, -0.13, 0.666], [1.25, 0.58, 0.36]));
        this.mouth = mesh(new THREE.CapsuleGeometry(0.033, 0.15, 5, 14), cyan, this.head, [0, -0.22, 0.704], [1, 0.28, 0.55]);
        this.antenna = new THREE.Group();
        this.antenna.position.set(0, 0.78, 0);
        this.head.add(this.antenna);
        const stem = mesh(new THREE.CylinderGeometry(0.025, 0.035, 0.19, 12), purple, this.antenna, [0, 0.09, 0]);
        stem.rotation.z = -0.12;
        this.antennaLight = mesh(new THREE.SphereGeometry(0.095, 24, 18), cyan, this.antenna, [0, 0.2, 0]);
        mesh(new THREE.TorusGeometry(0.11, 0.018, 8, 24), purple, this.antenna, [0, 0.2, 0]);
        [-1, 1].forEach(side => mesh(new THREE.SphereGeometry(0.13, 20, 16), purple, this.head, [side * 0.82, 0, 0.02], [0.8, 1, 0.55]));

        this.leftArm = makeArm(this.root, -1);
        this.rightArm = makeArm(this.root, 1);
        this.blinkAt = 2.4;
        this.resizeObserver = typeof ResizeObserver !== 'undefined'
            ? new ResizeObserver(() => this.resize())
            : null;
        this.observeMount();
        this.onResize = () => this.resize();
        window.addEventListener('resize', this.onResize, { passive: true });
        this.onVisibility = () => {
            if (document.hidden) {
                cancelAnimationFrame(this.raf);
                this.raf = null;
            } else if (this.raf === null) {
                this.raf = requestAnimationFrame(this.frame);
            }
        };
        document.addEventListener('visibilitychange', this.onVisibility);
        this.frame = this.frame.bind(this);
        this.raf = requestAnimationFrame(this.frame);
    }

    observeMount() {
        this.resizeObserver?.disconnect();
        if (this.canvas.parentElement) this.resizeObserver?.observe(this.canvas.parentElement);
        this.resize();
    }

    setState(state) {
        this.state = state;
    }

    resize() {
        const mount = this.canvas.parentElement;
        if (!mount) return;
        const width = Math.max(1, mount.clientWidth);
        const height = Math.max(1, mount.clientHeight);
        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        this.camera.position.z = width < 100 ? 5.9 : 5.4;
        this.camera.updateProjectionMatrix();
    }

    frame() {
        this.raf = null;
        if (document.hidden) return;
        if (!this.canvas.isConnected) {
            this.raf = requestAnimationFrame(this.frame);
            return;
        }
        const elapsed = this.clock.getElapsedTime();
        const target = animationPose(this.state, elapsed);
        const ease = 0.075;
        Object.keys(target).forEach(key => {
            this.currentPose[key] = (this.currentPose[key] ?? target[key]) + (target[key] - (this.currentPose[key] ?? target[key])) * ease;
        });

        this.root.position.y = this.currentPose.floatY;
        this.head.rotation.set(this.currentPose.headX, this.currentPose.headY, this.currentPose.headZ);
        this.leftArm.pivot.rotation.z = this.currentPose.leftArm;
        this.rightArm.pivot.rotation.z = this.currentPose.rightArm;
        this.mouth.scale.y = this.currentPose.mouth;
        const blinkWave = Math.max(0, 1 - Math.abs(elapsed - this.blinkAt) / 0.12);
        if (elapsed > this.blinkAt + 0.2) this.blinkAt = elapsed + 2.1 + Math.random() * 2.2;
        const eyeHeight = 1 - blinkWave * 0.92;
        this.eyes.forEach(eye => {
            eye.scale.y = eyeHeight;
            eye.position.y = eye.userData.baseY + this.currentPose.eyeY;
        });
        this.eyeIrisMeshes.forEach(iris => { iris.material.emissiveIntensity = this.currentPose.glow; });
        this.antennaLight.material.emissiveIntensity = this.currentPose.glow;
        this.renderer.render(this.scene, this.camera);
        this.raf = requestAnimationFrame(this.frame);
    }

    destroy() {
        cancelAnimationFrame(this.raf);
        window.removeEventListener('resize', this.onResize);
        document.removeEventListener('visibilitychange', this.onVisibility);
        this.resizeObserver?.disconnect();
        this.scene.traverse(item => {
            if (item.geometry) item.geometry.dispose();
            if (item.material) (Array.isArray(item.material) ? item.material : [item.material]).forEach(material => material.dispose());
        });
        this.renderer.dispose();
    }
}
