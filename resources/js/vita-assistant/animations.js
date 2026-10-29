const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export function animationPose(state, time) {
    const breath = Math.sin(time * 1.6);
    const sway = Math.sin(time * 1.1);
    const pose = {
        floatY: Math.sin(time * 1.35) * 0.055,
        headX: Math.sin(time * 0.8) * 0.045,
        headY: Math.sin(time * 0.55) * 0.12,
        headZ: Math.sin(time * 0.7) * 0.035,
        leftArm: -0.08 + sway * 0.04,
        rightArm: 0.08 - sway * 0.04,
        mouth: 0.12,
        eyeY: 0,
        glow: 0.45 + (breath + 1) * 0.14,
    };

    if (state === 'listening') {
        pose.headZ = -0.16;
        pose.headX = 0.08 + Math.sin(time * 1.5) * 0.025;
        pose.eyeY = -0.035;
        pose.leftArm = -0.24;
        pose.rightArm = 0.24;
        pose.glow = 0.72 + Math.sin(time * 4) * 0.12;
    } else if (state === 'thinking') {
        pose.headX = -0.18 + Math.sin(time * 1.1) * 0.025;
        pose.headY = 0.18;
        pose.eyeY = 0.12;
        pose.rightArm = -0.7 + Math.sin(time * 1.8) * 0.04;
        pose.glow = 0.7 + Math.sin(time * 3) * 0.16;
    } else if (state === 'talking') {
        pose.headY = Math.sin(time * 2.6) * 0.09;
        pose.leftArm = -0.15 - Math.sin(time * 3.5) * 0.18;
        pose.rightArm = 0.15 + Math.sin(time * 3.5 + 0.8) * 0.18;
        pose.mouth = 0.18 + Math.abs(Math.sin(time * 11)) * 0.8;
    } else if (state === 'happy') {
        pose.headX = -0.08 + Math.sin(time * 4) * 0.05;
        pose.headY = Math.sin(time * 2.8) * 0.16;
        pose.leftArm = -1.05 + Math.sin(time * 5) * 0.12;
        pose.rightArm = 1.05 - Math.sin(time * 5) * 0.12;
        pose.mouth = 0.82;
        pose.glow = 0.95;
    } else if (state === 'greeting') {
        pose.leftArm = -0.08;
        pose.rightArm = -1.2 + Math.sin(time * 7) * 0.42;
        pose.headY = Math.sin(time * 2) * 0.08;
        pose.mouth = 0.38;
    }

    pose.mouth = clamp(pose.mouth, 0.08, 1);
    return pose;
}
