import Logo from '../components/Logo.js'
import SlimsText from '../components/SlimsText.js'
import Features from '../components/Features.js'
import SlimsButton from '../components/Button.js'

class Particle {
    constructor(effect, x, y, color) {
        this.effect = effect
        this.color = color
        this.x = (Math.round(Math.random()) * 2 - 1) * Math.random() * this.effect.width + x
        this.y = (Math.round(Math.random()) * 2 - 1) * Math.random() * this.effect.height + y
        this.originX = Math.floor(x)
        this.originY = Math.floor(y)
        this.size = this.effect.gap-1
        this.vx = 0
        this.vy = 0
        this.easy = 0.05
        this.friction = 0.8
    }
    draw(context) {
        context.fillStyle = this.color
        context.fillRect(this.x, this.y, this.size, this.size)
    }
    update() {
        this.dx = this.effect.mouse.x - this.x
        this.dy = this.effect.mouse.y - this.y
        this.distance = this.dx*this.dx + this.dy*this.dy
        this.force = -this.effect.mouse.radius / this.distance

        if (this.distance < this.effect.mouse.radius) {
            this.angel = Math.atan2(this.dy, this.dx)
            this.vx += this.force * Math.cos(this.angel)
            this.vy += this.force * Math.sin(this.angel)
        }

        this.x += (this.vx *= this.friction) + (this.originX - this.x) * this.easy
        this.y += (this.vy *= this.friction) + (this.originY - this.y) * this.easy
    }
}

class Effect {
    constructor(width, height) {
        this.width = width
        this.height = height
        this.particles = []
        this.gap = 3
        this.mouse = {
            x: undefined,
            y: undefined,
            radius: 300
        }
        window.addEventListener('mousemove', e => {
            this.mouse.x = e.x
            this.mouse.y = e.y
        })
    }
    init(context) {
        this.context = context

        const logo = document.getElementById('logo')

        const centerX = this.width * 0.5
        const centerY = this.height * 0.5

        // draw image
        const logoX = centerX - logo.width * 0.5
        const logoY = centerY - logo.height * 1.4
        context.drawImage(logo, logoX, logoY, logo.width, logo.height)

        // draw text
        context.font = '32px Arial'
        // Kutubkhanah: the two Ks of the KK mark in gold
        const welcome = ['Welcome to ', 'K', 'utub', 'k', 'hanah']
        const welcomeStr = welcome.join('')
        let textWidth = context.measureText(welcomeStr).width
        let textStart = centerX - textWidth * 0.5
        let x = 0
        welcome.forEach(c => {
            context.fillStyle = (c === 'K' || c === 'k') ? 'rgb(212, 175, 55)' : 'white'
            context.fillText(c, textStart + x, centerY)
            x += context.measureText(c).width
        })

        // get pixels
        const pixels = this.context.getImageData(0, 0, this.width, this.height).data
        for (let y = 0; y < this.height; y += this.gap) {
            for (let x = 0; x < this.width; x += this.gap) {
                const index = (y * this.width + x) * 4
                const red = pixels[index]
                const green = pixels[index + 1]
                const blue = pixels[index + 2]
                const alpha = pixels[index + 3]
                const rgb = `rgb(${red}, ${green}, ${blue})`

                if (alpha > 0) this.particles.push(new Particle(this, x, y, rgb))
            }
        }
    }
    draw() {
        // draw particles
        this.particles.forEach(particle => particle.draw(this.context))
    }
    update() {
        this.particles.forEach(particle => particle.update())
    }
}

export default {
    components: {
        Logo,
        SlimsText,
        Features,
        SlimsButton
    },
    data() {
        return {
            effect: undefined,
            runAnimation: true
        }
    },
    beforeDestroy() {
        this.runAnimation = false
    },
    methods: {
        animate() {
            if (!this.runAnimation) return;
            this.effect.context.clearRect(0,0, this.effect.width, this.effect.height)
            this.effect.draw()
            this.effect.update()
            requestAnimationFrame(this.animate)
        },
        onLogoLoaded() {
            const canvas = document.getElementById('welcome')
            const ctx = canvas.getContext('2d')
            canvas.width = window.innerWidth * 0.5
            canvas.height = window.innerHeight
            this.effect = new Effect(canvas.width, canvas.height)
            this.effect.init(ctx)

            this.animate()
        }
    },
    template: `<section class="flex h-screen">
        <div class="w-1/2 p-4 flex flex-col">
            <div class="flex items-center hidden">
                <div class="w-12 mr-4">
                    <logo/>
                </div>
                <slims-text class="text-2xl text-gray-200" style="letter-spacing: 0.5em"/>
            </div>
            <div class="flex-1 flex flex-col justify-center items-center text-center text-gray-200 px-12">
                <canvas id="welcome" class="absolute left-0 top-0 z-0"></canvas>
                <img id="logo" class="hidden" @load="onLogoLoaded" src="../images/kutubkhanah/kk-mark-outline.png" width="120" height="120" alt="Kutubkhanah">
                <p class="mt-48 mb-4"><slims-text></slims-text> is a home library system that brings together SLiMS, PMB and OpenBiblio:
                    scan ISBNs, import from Libib and share your collection as a catalogue.</p>
                <slims-button class="z-10" @click="$emit('click')" text="Get Started"></slims-button>
            </div>
        </div>
        <div class="w-1/2 py-8">
            <div class="bg-gray-100 rounded-l-lg h-full py-4 px-16">
                <features></features>
            </div>
        </div>
    </section>`
}
