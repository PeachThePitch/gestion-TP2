<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.")]
    private ?string $adresse = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La ville est obligatoire.")]
    private ?string $ville = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: "Le code postal est obligatoire.")]
    #[Assert\Regex(
        pattern: "/^[0-9]{5}$/",
        message: "Le code postal doit comporter 5 chiffres."
    )]
    private ?string $codePostal = null;

    #[ORM\Column]
    private ?bool $isSuspended = false;

    /**
     * @var Collection<int, PasswordHistory>
     */
    #[ORM\OneToMany(targetEntity: PasswordHistory::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $passwordHistories;

    /**
     * @var Collection<int, LoginLog>
     */
    #[ORM\OneToMany(targetEntity: LoginLog::class, mappedBy: 'user')]
    private Collection $loginLogs;

    public function __construct()
    {
        $this->passwordHistories = new ArrayCollection();
        $this->loginLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function isSuspended(): ?bool
    {
        return $this->isSuspended;
    }

    public function setIsSuspended(bool $isSuspended): static
    {
        $this->isSuspended = $isSuspended;

        return $this;
    }

    /**
     * @return Collection<int, PasswordHistory>
     */
    public function getPasswordHistories(): Collection
    {
        return $this->passwordHistories;
    }

    public function addPasswordHistory(PasswordHistory $passwordHistory): static
    {
        if (!$this->passwordHistories->contains($passwordHistory)) {
            $this->passwordHistories->add($passwordHistory);
            $passwordHistory->setUser($this);
        }

        return $this;
    }

    public function removePasswordHistory(PasswordHistory $passwordHistory): static
    {
        if ($this->passwordHistories->removeElement($passwordHistory)) {
            // set the owning side to null (unless already changed)
            if ($passwordHistory->getUser() === $this) {
                $passwordHistory->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LoginLog>
     */
    public function getLoginLogs(): Collection
    {
        return $this->loginLogs;
    }

    public function addLoginLog(LoginLog $loginLog): static
    {
        if (!$this->loginLogs->contains($loginLog)) {
            $this->loginLogs->add($loginLog);
            $loginLog->setUser($this);
        }

        return $this;
    }

    public function removeLoginLog(LoginLog $loginLog): static
    {
        if ($this->loginLogs->removeElement($loginLog)) {
            // set the owning side to null (unless already changed)
            if ($loginLog->getUser() === $this) {
                $loginLog->setUser(null);
            }
        }

        return $this;
    }
}
