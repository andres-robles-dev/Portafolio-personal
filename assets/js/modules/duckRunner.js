const STORAGE_KEY = "duckRunnerHighScore";

const WORLD_WIDTH = 900;
const WORLD_HEIGHT = 220;
const GROUND_Y = 178;

const FIXED_STEP_MS = 1000 / 60;
const GRAVITY = 0.62;
const JUMP_VELOCITY = -11.8;
const FAST_FALL_BOOST = 0.75;

const START_SPEED = 6;
const MAX_SPEED = 14;
const SPEED_GAIN = 0.0016;

const DUCK_X = 84;
const DUCK_RUN_WIDTH = 36;
const DUCK_RUN_HEIGHT = 32;
const DUCK_CROUCH_WIDTH = 42;
const DUCK_CROUCH_HEIGHT = 24;

function randomBetween(min, max) {
    return min + Math.random() * (max - min);
}

function intersects(a, b) {
    return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
}

function formatScore(value) {
    return String(Math.max(0, Math.floor(value))).padStart(5, "0");
}

function readHighScore() {
    try {
        return Number(window.localStorage.getItem(STORAGE_KEY)) || 0;
    } catch {
        return 0;
    }
}

function persistHighScore(value) {
    try {
        window.localStorage.setItem(STORAGE_KEY, String(value));
    } catch {
        return;
    }
}

function createInitialClouds() {
    return [
        { x: 240, y: 38, w: 58, h: 14, speed: 0.85 },
        { x: 560, y: 74, w: 44, h: 12, speed: 1.05 }
    ];
}

function createGroundBits() {
    return Array.from({ length: 16 }, (_, index) => ({
        x: index * 66,
        y: GROUND_Y + randomBetween(7, 16),
        w: randomBetween(3, 12),
        h: 2
    }));
}

function createObstacleForSpeed(speed) {
    const canSpawnFlyer = speed >= 8.2;
    const roll = Math.random();

    if (canSpawnFlyer && roll > 0.82) {
        return {
            type: "flyer-low",
            x: WORLD_WIDTH + 20,
            y: 140,
            w: 28,
            h: 14,
            anim: 0,
            hitbox: { x: 2, y: 2, w: 24, h: 10 }
        };
    }

    if (roll < 0.28) {
        return {
            type: "small",
            x: WORLD_WIDTH + 20,
            y: GROUND_Y - 28,
            w: 16,
            h: 28,
            hitbox: { x: 2, y: 2, w: 12, h: 25 }
        };
    }

    if (roll < 0.58) {
        return {
            type: "wide",
            x: WORLD_WIDTH + 20,
            y: GROUND_Y - 24,
            w: 30,
            h: 24,
            hitbox: { x: 2, y: 2, w: 26, h: 20 }
        };
    }

    if (roll < 0.84) {
        return {
            type: "tall",
            x: WORLD_WIDTH + 20,
            y: GROUND_Y - 38,
            w: 22,
            h: 38,
            hitbox: { x: 3, y: 2, w: 16, h: 34 }
        };
    }

    return {
        type: "double",
        x: WORLD_WIDTH + 20,
        y: GROUND_Y - 30,
        w: 34,
        h: 30,
        hitbox: { x: 2, y: 3, w: 30, h: 24 }
    };
}

function computeNextSpawnDistance(speed, obstacleType) {
    let min = Math.max(132, 250 - speed * 9);
    let max = Math.max(min + 70, 390 - speed * 7);

    if (obstacleType === "flyer-low") {
        min += 20;
        max += 60;
    }

    if (obstacleType === "double") {
        min += 16;
        max += 34;
    }

    return randomBetween(min, max);
}

function drawCloud(ctx, cloud) {
    const x = Math.round(cloud.x);
    const y = Math.round(cloud.y);

    ctx.fillStyle = "rgba(242,242,242,0.34)";
    ctx.fillRect(x + 6, y, cloud.w - 12, cloud.h - 6);
    ctx.fillRect(x, y + 4, cloud.w, cloud.h - 6);
    ctx.fillRect(x + 10, y + cloud.h - 3, cloud.w - 20, 2);
}

function drawObstacle(ctx, obstacle) {
    const x = Math.round(obstacle.x);
    const y = Math.round(obstacle.y);

    ctx.fillStyle = "#f2f2f2";

    if (obstacle.type === "small") {
        ctx.fillRect(x + 6, y, 4, obstacle.h);
        ctx.fillRect(x + 2, y + 8, 4, 10);
        ctx.fillRect(x + 10, y + 12, 4, 8);
        return;
    }

    if (obstacle.type === "wide") {
        ctx.fillRect(x + 12, y + 2, 4, obstacle.h - 2);
        ctx.fillRect(x + 4, y + 11, 8, 4);
        ctx.fillRect(x + 16, y + 14, 8, 4);
        return;
    }

    if (obstacle.type === "tall") {
        ctx.fillRect(x + 9, y, 4, obstacle.h);
        ctx.fillRect(x + 5, y + 8, 4, 12);
        ctx.fillRect(x + 13, y + 14, 4, 12);
        return;
    }

    if (obstacle.type === "double") {
        ctx.fillRect(x + 3, y + 8, 5, obstacle.h - 8);
        ctx.fillRect(x + 11, y + 2, 5, obstacle.h - 2);
        ctx.fillRect(x + 19, y + 10, 5, obstacle.h - 10);
        return;
    }

    const flap = Math.floor(obstacle.anim / 6) % 2;
    const wingY = flap === 0 ? 2 : 4;

    ctx.fillRect(x + 8, y + 3, 10, 6);
    ctx.fillRect(x + 18, y + 4, 4, 4);
    ctx.fillRect(x + 4, y + wingY, 6, 2);
    ctx.fillRect(x + 4, y + wingY + 6, 6, 2);
}

function drawDuck(ctx, duck) {
    const unit = 2;
    const x = Math.round(duck.x);
    const y = Math.round(duck.y);

    function rect(rx, ry, rw, rh) {
        ctx.fillRect(x + rx * unit, y + ry * unit, rw * unit, rh * unit);
    }

    ctx.fillStyle = "#f2f2f2";

    if (duck.ducking && duck.onGround) {
        rect(2, 8, 12, 5);
        rect(1, 9, 2, 3);
        rect(12, 7, 4, 4);
        rect(15, 8, 2, 2);
        rect(5, 13, 2, 2);
        rect(10, 13, 2, 2);
        rect(13, 13, 2, 2);
        return;
    }

    rect(3, 7, 9, 6);
    rect(2, 8, 2, 3);
    rect(11, 5, 4, 5);
    rect(13, 4, 3, 4);
    rect(15, 6, 2, 2);
    rect(8, 8, 3, 3);

    if (duck.onGround) {
        if (duck.frame === 0) {
            rect(6, 13, 2, 3);
            rect(11, 14, 2, 2);
        } else {
            rect(6, 14, 2, 2);
            rect(11, 13, 2, 3);
        }
    } else {
        rect(7, 14, 2, 2);
        rect(10, 14, 2, 2);
    }
}

export function initDuckRunner() {
    const section = document.getElementById("duckRunnerSection");
    const canvas = document.getElementById("duckRunnerCanvas");
    const stage = document.getElementById("duckRunnerStage");
    const overlay = document.getElementById("duckRunnerOverlay");
    const overlayTitle = document.getElementById("duckRunnerOverlayTitle");
    const overlayHint = document.getElementById("duckRunnerOverlayHint");
    const scoreNode = document.getElementById("duckRunnerScore");
    const highScoreNode = document.getElementById("duckRunnerHighScore");

    if (!section || !canvas || !stage || !overlay || !overlayTitle || !overlayHint || !scoreNode || !highScoreNode) {
        return;
    }

    const ctx = canvas.getContext("2d");

    if (!ctx) {
        return;
    }

    let state = "ready";
    let visible = false;
    let score = 0;
    let highScore = readHighScore();
    let speed = START_SPEED;
    let distanceSinceSpawn = 0;
    let nextSpawnDistance = randomBetween(230, 330);
    let lastTime = 0;
    let accumulator = 0;
    let rafId = 0;

    let groundOffset = 0;
    let obstacles = [];
    let clouds = createInitialClouds();
    let groundBits = createGroundBits();
    let cloudTimer = 0;
    let nextCloudTick = randomBetween(120, 210);

    const duck = {
        x: DUCK_X,
        y: GROUND_Y - DUCK_RUN_HEIGHT,
        width: DUCK_RUN_WIDTH,
        height: DUCK_RUN_HEIGHT,
        vy: 0,
        onGround: true,
        frame: 0,
        frameTimer: 0,
        ducking: false,
        duckingRequested: false,
        fastFallRequested: false
    };

    function setOverlay(title, hint, hidden) {
        overlayTitle.textContent = title;
        overlayHint.textContent = hint;
        overlay.classList.toggle("is-hidden", hidden);
    }

    function updateHud() {
        scoreNode.textContent = formatScore(score);
        highScoreNode.textContent = formatScore(highScore);
    }

    function resetRun() {
        state = "ready";
        score = 0;
        speed = START_SPEED;
        distanceSinceSpawn = 0;
        nextSpawnDistance = randomBetween(230, 330);

        groundOffset = 0;
        obstacles = [];
        clouds = createInitialClouds();
        groundBits = createGroundBits();
        cloudTimer = 0;
        nextCloudTick = randomBetween(120, 210);

        duck.y = GROUND_Y - DUCK_RUN_HEIGHT;
        duck.width = DUCK_RUN_WIDTH;
        duck.height = DUCK_RUN_HEIGHT;
        duck.vy = 0;
        duck.onGround = true;
        duck.frame = 0;
        duck.frameTimer = 0;
        duck.ducking = false;
        duck.duckingRequested = false;
        duck.fastFallRequested = false;

        setOverlay("Duck Runner", "Pulsa SPACE / UP / DOWN o TAP para empezar", false);
        updateHud();
    }

    function setGameOver() {
        state = "gameover";

        if (score > highScore) {
            highScore = Math.floor(score);
            persistHighScore(highScore);
        }

        setOverlay("Game Over", "Pulsa SPACE o TAP para reiniciar", false);
        updateHud();
    }

    function jump() {
        if (!duck.onGround) {
            return;
        }

        duck.vy = JUMP_VELOCITY;
        duck.onGround = false;
        duck.ducking = false;
    }

    function updateDuckingState() {
        const shouldDuck = duck.duckingRequested && duck.onGround;

        duck.ducking = shouldDuck;

        if (shouldDuck) {
            duck.width = DUCK_CROUCH_WIDTH;
            duck.height = DUCK_CROUCH_HEIGHT;
        } else {
            duck.width = DUCK_RUN_WIDTH;
            duck.height = DUCK_RUN_HEIGHT;
        }

        if (duck.onGround) {
            duck.y = GROUND_Y - duck.height;
        }
    }

    function startRun(withJump = false) {
        if (state === "gameover") {
            resetRun();
        }

        if (state === "ready") {
            state = "running";
            setOverlay("", "", true);
        }

        if (withJump) {
            jump();
        }
    }

    function shouldRunFrame() {
        return visible && document.visibilityState === "visible";
    }

    function getDuckHitbox() {
        if (duck.ducking && duck.onGround) {
            return {
                x: duck.x + 6,
                y: duck.y + 3,
                w: duck.width - 12,
                h: duck.height - 4
            };
        }

        return {
            x: duck.x + 7,
            y: duck.y + 5,
            w: duck.width - 14,
            h: duck.height - 7
        };
    }

    function updateDuck() {
        updateDuckingState();

        if (!duck.onGround) {
            duck.vy += GRAVITY;

            if (duck.fastFallRequested) {
                duck.vy += FAST_FALL_BOOST;
            }

            duck.y += duck.vy;

            if (duck.y >= GROUND_Y - duck.height) {
                duck.y = GROUND_Y - duck.height;
                duck.vy = 0;
                duck.onGround = true;
            }
        }

        if (duck.onGround && !duck.ducking) {
            duck.frameTimer += speed;

            if (duck.frameTimer > 46) {
                duck.frame = duck.frame === 0 ? 1 : 0;
                duck.frameTimer = 0;
            }
        }
    }

    function updateClouds() {
        cloudTimer += speed;

        if (cloudTimer >= nextCloudTick) {
            clouds.push({
                x: WORLD_WIDTH + randomBetween(20, 120),
                y: randomBetween(26, 88),
                w: randomBetween(38, 66),
                h: randomBetween(10, 16),
                speed: randomBetween(0.75, 1.2)
            });

            cloudTimer = 0;
            nextCloudTick = randomBetween(130, 240);
        }

        clouds = clouds.filter((cloud) => {
            cloud.x -= cloud.speed;
            return cloud.x + cloud.w > -60;
        });
    }

    function updateGroundBits() {
        groundBits.forEach((bit) => {
            bit.x -= speed;
        });

        const rightMost = groundBits.reduce((max, bit) => Math.max(max, bit.x), 0);

        groundBits = groundBits.filter((bit) => bit.x + bit.w > -12);

        while (groundBits.length < 16) {
            groundBits.push({
                x: rightMost + randomBetween(28, 96) + groundBits.length * 3,
                y: GROUND_Y + randomBetween(7, 16),
                w: randomBetween(3, 12),
                h: 2
            });
        }
    }

    function updateObstacles() {
        distanceSinceSpawn += speed;

        if (distanceSinceSpawn >= nextSpawnDistance) {
            const obstacle = createObstacleForSpeed(speed);
            obstacles.push(obstacle);

            if (speed >= 10 && obstacle.type === "small" && Math.random() > 0.68) {
                const paired = {
                    ...createObstacleForSpeed(speed),
                    x: obstacle.x + randomBetween(90, 132)
                };

                if (paired.type !== "flyer-low") {
                    obstacles.push(paired);
                }
            }

            distanceSinceSpawn = 0;
            nextSpawnDistance = computeNextSpawnDistance(speed, obstacle.type);
        }

        obstacles = obstacles.filter((obstacle) => {
            obstacle.x -= speed;

            if (obstacle.type === "flyer-low") {
                obstacle.anim += 1;
            }

            return obstacle.x + obstacle.w > -40;
        });
    }

    function checkCollisions() {
        const duckHitbox = getDuckHitbox();

        for (const obstacle of obstacles) {
            const hitbox = {
                x: obstacle.x + obstacle.hitbox.x,
                y: obstacle.y + obstacle.hitbox.y,
                w: obstacle.hitbox.w,
                h: obstacle.hitbox.h
            };

            if (intersects(duckHitbox, hitbox)) {
                setGameOver();
                return;
            }
        }
    }

    function updateGame() {
        if (state !== "running") {
            return;
        }

        speed = Math.min(MAX_SPEED, speed + SPEED_GAIN);
        score += speed * 0.1;
        groundOffset = (groundOffset + speed) % 24;

        updateDuck();
        updateClouds();
        updateGroundBits();
        updateObstacles();
        checkCollisions();
        updateHud();
    }

    function drawBackground() {
        ctx.clearRect(0, 0, WORLD_WIDTH, WORLD_HEIGHT);
        ctx.fillStyle = "#000";
        ctx.fillRect(0, 0, WORLD_WIDTH, WORLD_HEIGHT);

        for (const cloud of clouds) {
            drawCloud(ctx, cloud);
        }
    }

    function drawGround() {
        ctx.fillStyle = "#f2f2f2";
        ctx.fillRect(0, GROUND_Y, WORLD_WIDTH, 2);

        const dashStep = 24;
        const dashWidth = 10;

        for (let x = -groundOffset; x < WORLD_WIDTH + dashStep; x += dashStep) {
            ctx.fillRect(Math.round(x), GROUND_Y + 5, dashWidth, 2);
        }

        for (const bit of groundBits) {
            ctx.fillRect(Math.round(bit.x), Math.round(bit.y), Math.round(bit.w), bit.h);
        }
    }

    function drawGame() {
        drawBackground();
        drawGround();

        for (const obstacle of obstacles) {
            drawObstacle(ctx, obstacle);
        }

        drawDuck(ctx, duck);
    }

    function handleAction() {
        if (state === "gameover") {
            resetRun();
            startRun(true);
            return;
        }

        if (state === "ready") {
            startRun(true);
            return;
        }

        jump();
    }

    function isTypingInField() {
        const active = document.activeElement;

        if (!(active instanceof HTMLElement)) {
            return false;
        }

        const tag = active.tagName;
        return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || active.isContentEditable;
    }

    function handleKeyDown(event) {
        if (!visible || isTypingInField()) {
            return;
        }

        const jumpKey = event.code === "Space" || event.code === "ArrowUp" || event.code === "KeyW";
        const duckKey = event.code === "ArrowDown" || event.code === "KeyS";

        if (jumpKey && !event.repeat) {
            event.preventDefault();
            handleAction();
            return;
        }

        if (duckKey) {
            event.preventDefault();
            duck.duckingRequested = true;

            if (!duck.onGround) {
                duck.fastFallRequested = true;
            }
        }
    }

    function handleKeyUp(event) {
        const duckKey = event.code === "ArrowDown" || event.code === "KeyS";

        if (!duckKey) {
            return;
        }

        duck.duckingRequested = false;
        duck.fastFallRequested = false;
    }

    function frame(time) {
        if (!lastTime) {
            lastTime = time;
        }

        let deltaMs = time - lastTime;
        lastTime = time;

        if (deltaMs > 120) {
            deltaMs = 120;
        }

        if (shouldRunFrame()) {
            accumulator += deltaMs;

            while (accumulator >= FIXED_STEP_MS) {
                updateGame();
                accumulator -= FIXED_STEP_MS;
            }
        } else {
            accumulator = 0;
        }

        drawGame();
        rafId = window.requestAnimationFrame(frame);
    }

    function applyCanvasResolution() {
        const devicePixelRatio = Math.max(1, Math.min(2, window.devicePixelRatio || 1));

        canvas.width = Math.floor(WORLD_WIDTH * devicePixelRatio);
        canvas.height = Math.floor(WORLD_HEIGHT * devicePixelRatio);

        ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
        ctx.imageSmoothingEnabled = false;
    }

    const visibilityObserver = new IntersectionObserver((entries) => {
        const entry = entries[0];
        visible = Boolean(entry && entry.isIntersecting && entry.intersectionRatio > 0.2);
    }, {
        threshold: [0.05, 0.2, 0.4]
    });

    visibilityObserver.observe(section);

    applyCanvasResolution();
    resetRun();

    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("keyup", handleKeyUp);

    window.addEventListener("resize", applyCanvasResolution, { passive: true });
    document.addEventListener("visibilitychange", () => {
        lastTime = 0;
    });

    stage.addEventListener("pointerdown", (event) => {
        event.preventDefault();
        handleAction();
    });

    rafId = window.requestAnimationFrame(frame);

    return () => {
        window.cancelAnimationFrame(rafId);
        window.removeEventListener("keydown", handleKeyDown);
        window.removeEventListener("keyup", handleKeyUp);
        window.removeEventListener("resize", applyCanvasResolution);
        visibilityObserver.disconnect();
    };
}
