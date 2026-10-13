import * as THREE from 'three';

function disposeMaterial(material: THREE.Material): void {
    for (const value of Object.values(material)) {
        if (value instanceof THREE.Texture) {
            value.dispose();
        }
    }

    material.dispose();
}

/**
 * Frees everything under a node: geometries, materials, their textures and the
 * instance buffers of every InstancedMesh. Three.js does not do this on its
 * own, and leaving the page must leave nothing on the GPU.
 */
export function disposeTree(root: THREE.Object3D): void {
    root.traverse((node) => {
        const mesh = node as THREE.Mesh;

        mesh.geometry?.dispose();

        const material = mesh.material;

        if (Array.isArray(material)) {
            material.forEach(disposeMaterial);
        } else if (material) {
            disposeMaterial(material);
        }

        if (node instanceof THREE.InstancedMesh) {
            node.dispose();
        }
    });
    root.removeFromParent();
}
